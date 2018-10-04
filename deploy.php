<?php
namespace Deployer;

require 'recipe/common.php';

// Project path
set('project_path', __DIR__ . '');
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

const RSYNC_OPTS = array(
  'options' => [
    '-L',
    '-K',
    '--delete'
  ]
);

// Wordpress tasks
desc('Check if wp_path on host is valid');
task('wp:check', function() {
  check_required_param(['bin/wp', 'wp_path']);
  $check_cmd = "{{bin/wp}} db query 'SELECT database()'";
  if (! test("cd {{wp_path}}; [[ $($check_cmd) = *\"database\"* ]]")) {
    writeln('<error>This does not seem to be a WordPress install in "{{wp_path}}".</error>');
    exit;
  }
  $wp_summ = run('cd {{wp_path}}; VER=$({{bin/wp}} core version); URL=$({{bin/wp}} option get siteurl); echo $VER,$URL');
  list($wp_ver, $wp_siteurl) = explode(',', $wp_summ);
  writeln("<info>Current hostname is \"{{hostname}}\",\n public path is \"{{wp_path}}\"\n has installed Wordpress version {$wp_ver}\n and published at {$wp_siteurl}</info>");
  if (!askConfirmation('Are you sure you want to deploy (and OVERRIDE) your Wordpress website to this location?')) {
    exit;
  }
});

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

desc('Upload themes to host');
task('push:themes', function() {
  upload('{{content_path}}/themes', '{{release_path}}', RSYNC_OPTS);
});

desc('Download themes from host');
task('pull:themes', function() {
  download('{{wp_path}}/wp-content/themes', '{{content_path}}');
});

desc('Upload only activate theme to host');
task('push:theme', function() {
  $theme_name = runLocally('wp theme list --status=active --field=name');
  if ($theme_name) {
    upload("{{content_path}}/themes/$theme_name", "{{release_path}}/themes", RSYNC_OPTS);
  }
});

desc('Download only activate theme from host');
task('pull:theme', function() {
  $theme_name = runLocally('wp theme list --status=active --field=name');
  if ($theme_name) {
  download("{{wp_path}}/wp-content/themes/$theme_name", "{{content_path}}/themes", RSYNC_OPTS);
  }
});

desc('Upload media files to host');
task('push:media', function() {
  upload('{{content_path}}/uploads', '{{deploy_path}}/shared');
});

desc('Download media files from host');
task('pull:media', function() {
  if (askConfirmation('Please make sure you have a BACKUP of your current "uploads" dir if needed. Continue?')) {
    download('{{wp_path}}/wp-content/uploads/', '{{content_path}}/uploads', RSYNC_OPTS);
  }
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
  $db_file = run("cd {{wp_path}} && {{bin/wp}} db export");
  $db_file = explode("'", $db_file);
  $db_file = $db_file[1];
  download("{{wp_path}}/$db_file", "{{project_path}}/.data");
  run("rm {{wp_path}}/$db_file");
  writeln("<info>Remote database is downloaded at \".data/$db_file\"</info>");
  if (askConfirmation('Do you want to import then remove it?')) {
    $local_url = runLocally("wp option get siteurl");
    runLocally("wp db import {{project_path}}/.data/$db_file && rm {{project_path}}/.data/$db_file");
    $public_url = runLocally("wp option get siteurl");
    runLocally("wp search-replace --all-tables $public_url $local_url");
  }
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
  // Backup old content (wp-content dir and database)
  if (test("[ ! -h $(echo {{wp_path}}/wp-content) ]")) {
    run("cd {{wp_path}}; mv wp-content wp-content.bak; {{bin/wp}} db export wp-content.bak/wpdb_snapshot.backp.sql");
  }
  run("cd {{wp_path}}; {{bin/symlink}} {{deploy_path}}/current wp-content");
  writeln("<warning>We do not deploy your locally \"wp-config.php\" to host.\nSo that, if you have any custom configs, please do it manually by yourself.</warning>");
});

// Archive current project
desc('Archive current WP project');
task('wp:archive', function() {
  $rand_str = date('Y-m-d');
  $site_url = runLocally("wp option get home");
  $host = parse_url($site_url)['host'];
  $cmd_export_db = "wp db export ";
  if (has('public_url') && askConfirmation(sprintf("Replace home_url with \"%s\"?", get('public_url')))) {
    $host = parse_url(get('public_url'))['host'];
    $cmd_export_db = "wp search-replace $site_url {{public_url}} --export=";
  }
  $db_file = "$host.$rand_str.sql";
  $plg_file = "plugins.csv";
  $gz_file = "$host-$rand_str.tar.gz";
  runLocally("$cmd_export_db$db_file && wp plugin list --status=active --format=csv > $plg_file && tar -chf .data/$gz_file www/wp-content $plg_file $db_file && rm $db_file; rm $plg_file");
  writeln("<info>Current project data is archived at \".data/$gz_file\"</info>");
});

// Deploy flow
desc('Deploy your WP project');
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

after('deploy:prepare', 'wp:check');
