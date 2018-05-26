//webpack.config.js

const webpack               = require('webpack')

const BrowserSyncHotPlugin  = require('browser-sync-dev-hot-webpack-plugin')
const CleanWebpackPlugin    = require('clean-webpack-plugin')
const ExtractTextPlugin     = require('extract-text-webpack-plugin')
const ImageminPlugin        = require('imagemin-webpack-plugin').default
const path                  = require('path')
const WebpackAssetsManifest = require('webpack-assets-manifest')

require('dotenv').config()

const BROWSER_SYNC_OPTIONS = {
  https: false,
  host: 'localhost',
  port: 3000,
  proxy: [ 'http://', process.env.WPSITE_URL, process.env.WPSITE_PORT ? ':' + process.env.WPSITE_PORT : '' ].join(''),
  open: false,
  files: [
    'src/**/*.js',
    'src/**/*.php'
  ],
  watchOptions: {
    ignored: [
      '.data/**/*',
      'node_modules/**/*'
    ],
  }
};
const DEV_MIDDLEWARE_OPTIONS = {};
const HOT_MIDDLEWARE_OPTIONS = {};

/******************************************************************************/
module.exports = (env = {}) => {

  const isProduction = env.production === true;
  const isDevelopment = env.production !== true;

  let config = {
    entry: {
      main: [
        "./src/assets/app.js",
      ],
      customizer: "./src/assets/scripts/customizer.js",
      images: "./src/assets/scripts/images.js",

    },
    output: {
      path: __dirname + "/src/dist/",
      filename: isProduction ? 'scripts/[name]-[hash].js' : 'scripts/[name].js',
      publicPath: '/wp-content/themes/' + process.env.WPSITE_THEME_NAME + '/dist/'
    },
    externals: {
      jquery: 'jQuery',
    },
    // devtool: isProduction ? "source-map" : "cheap-eval-source-map",
    devtool: isProduction ? "nosources-source-map" : "eval",
    module: {
      rules: [{
        test: /\.scss$/,
        include: path.resolve(__dirname, 'src/assets'),
        use: ExtractTextPlugin.extract({
          use: [{
            loader: "css-loader", options: {
              sourceMap: true
            }
          }, {
            loader: "postcss-loader", options: {
              sourceMap: true,
              config: {
                path: 'configs/webpack/postcss.config.js'
              }
            }
          }, {
            loader: "sass-loader", options: {
              sourceMap: true
            }
          }],
          // use style-loader in development
          fallback: "style-loader"
        })
      }, {
        test: /\.(png|jpg|jpeg|gif|svg)$/,
        include: path.resolve(__dirname, 'src/assets/images'),
        use: [
          {
            loader: 'file-loader',
            options: {
              name: isProduction ? "images/[name]-[hash].[ext]" : "images/[name].[ext]",
            }
          }
        ]
      }, {
        test: /\.(png|jpg|jpeg|gif|svg)$/,
        include: path.resolve(__dirname, 'src/assets/css-images'),
        use: [
          {
            loader: 'url-loader',
            options: {
              name: isProduction ? "images/[name]-[hash].[ext]" : "images/[name].[ext]",
              limit: 8192
            }
          }
        ]
      }, {
        test: /\.js$/,
        include: path.resolve(__dirname, 'src/assets'),
        use: [{
          loader: 'babel-loader',
          options: {
            presets: [["env"]]
          }
        }]
      }]
    },
    plugins: [
      new webpack.ProvidePlugin({
        $: 'jquery',
        jQuery: 'jquery',
        'window.jQuery': 'jquery',
        Popper: ['popper.js', 'default'],
      }),
      isProduction && new CleanWebpackPlugin('dist', { root: path.resolve(__dirname, 'src') }),
      isDevelopment && new BrowserSyncHotPlugin({
        browserSync: BROWSER_SYNC_OPTIONS,
        devMiddleware: DEV_MIDDLEWARE_OPTIONS,
        hotMiddleware: HOT_MIDDLEWARE_OPTIONS,
        callback() {
          // console.log('Callback');
          /*
            // Use browser sync server api (https://browsersync.io/docs/api)
            const { watcher: bs } = this;
            bs.notify("Hello! It's callback function from BrowserSyncHotPlugin!");
          */
        }
      }),
      new WebpackAssetsManifest({
        output: 'assets.json',
        replacer: require('./configs/webpack/assetManifestsFormatter')
        }),
      new ImageminPlugin({
         test: '/\.(jpe?g|png|gif|svg)$/i',
         disable: isDevelopment
      }),
      isProduction && new webpack.optimize.UglifyJsPlugin({
        minimize: true,
        sourceMap: true,
        output: { comments: false }
      }),
      new ExtractTextPlugin({
        filename: isProduction ? "styles/[name]-[contenthash].css" : "styles/[name].css",
        disable: isDevelopment
      }),
    ].filter(Boolean)
  }

  // hmr
  if (isDevelopment) {
    config.entry.main.push('webpack/hot/dev-server');
    config.entry.main.push('webpack-hot-middleware/client');
    config.plugins.push(new webpack.HotModuleReplacementPlugin());
  }

  return config
}
