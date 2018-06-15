<?php
namespace Deployer;

require 'recipe/common.php';

// Project path
set('docroot_path', __DIR__ . '/www');
set('content_path', __DIR__ . '/www/wp-content');

// Shared files/dirs between deploys
set('shared_files', []);
set('shared_dirs', ['uploads']);

// Writable dirs by web server
set('writable_dirs', ['uploads']);

// Hosts
inventory('hosts.yml');

function check_required_param($params_name) {
  if (is_string($params_name)) {
    $params_name = array( $params_name );
  }
  foreach ($params_name as $param_name) {
    if (!has($param_name)) {
      writeln("<error>Missing required parameter \"$param_name\".</error>");
      exit;
    }
  }
}

function get_rsync_options() {
  return array(
    'options' => [
      '-L',
      '-K',
      '--delete'
    ]
  );
}

// Wordpress tasks
desc('Upload plugins to host');
task('push:plugins', function() {
  upload('{{content_path}}/plugins', '{{release_path}}');
  upload('{{content_path}}/mu-plugins', '{{release_path}}');
});

desc('Download plugins from host');
task('pull:plugins', function() {
  download('{{wp_path}}/wp-content/plugins', '{{content_path}}');
  download('{{wp_path}}/wp-content/mu-plugins', '{{content_path}}');
});

task('push:themes:test', function() {
  invoke('push:themes');
});

desc('Upload themes to host');
task('push:themes', function() {
  upload('{{content_path}}/themes', '{{release_path}}', get_rsync_options());
});

desc('Download themes from host');
task('pull:themes', function() {
  download('{{wp_path}}/wp-content/themes', '{{content_path}}');
});

desc('Upload only activate theme to host');
task('push:theme', function() {
  $theme_name = runLocally('wp theme list --status=active --field=name');
  if ($theme_name) {
  upload("{{content_path}}/themes/$theme_name", "{{release_path}}/themes");
  }
});

desc('Download only activate theme from host');
task('pull:theme', function() {
  $theme_name = runLocally('wp theme list --status=active --field=name');
  if ($theme_name) {
  download("{{wp_path}}/wp-content/themes/$theme_name", "{{content_path}}/themes");
  }
});

desc('Upload media files to host');
task('push:media', function() {
  upload('{{content_path}}/uploads', '{{deploy_path}}/shared');
});

desc('Download media files from host');
task('pull:media', function() {
  download('{{wp_path}}/wp-content/uploads/', '{{content_path}}/uploads');
});

desc('Upload and import local database to host');
task('push:db', function() {
  check_required_param(['bin/wp', 'public_url']);
  $local_url = runLocally("wp option get siteurl");
  $db_file = runLocally("wp db export");
  $db_file = explode("'", $db_file);
  $db_file = $db_file[1];
  upload($db_file, "{{release_path}}");
  run("cd {{wp_path}} && {{bin/wp}} db import {{release_path}}/$db_file && {{bin/wp}} search-replace --all-tables $local_url {{public_url}} && rm {{release_path}}/$db_file");
  runLocally("rm $db_file");
});

desc('Download database from host and import to local');
task('pull:db', function() {
  check_required_param(['bin/wp', 'public_url']);
  $local_url = runLocally("wp option get siteurl");
  $db_file = run("cd {{wp_path}} && {{bin/wp}} db export");
  $db_file = explode("'", $db_file);
  $db_file = $db_file[1];
  download("{{wp_path}}/$db_file", "{{docroot_path}}");
  run("rm {{wp_path}}/$db_file");
  runLocally("wp db import {{docroot_path}}/$db_file && rm {{docroot_path}}/$db_file");
  $public_url = runLocally("wp option get siteurl");
  runLocally("wp search-replace --all-tables $public_url $local_url");
});

// Additional tasks
task('deploy:update_code', [
  'push:plugins',
  'push:themes',
  'push:media'
]);

task('deploy:update_db', [
  'push:db'
]);

// Symlink wp-content dir
desc('Link wp-content folder on host to current release');
task('deploy:symlink_wp', function() {
  // Backup if wp-content dir exists
  if (test("[ ! -h $(echo {{wp_path}}/wp-content) ]")) {
    run("mv {{wp_path}}/wp-content {{wp_path}}/wp-content.bak");
  }
  run("cd {{wp_path}} && {{bin/symlink}} {{deploy_path}}/current wp-content");
});

// Deploy flow
desc('Deploy your project');
task('deploy', [
  'deploy:info',
  'deploy:prepare',
  'deploy:lock',
  'deploy:release',
  'deploy:update_code',
  'deploy:update_db',
  'deploy:shared',
  'deploy:writable',
  // 'deploy:vendors',
  'deploy:clear_paths',
  'deploy:symlink',
  'deploy:symlink_wp',
  'deploy:unlock',
  'cleanup',
  'success'
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
