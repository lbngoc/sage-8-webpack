const postcssConfig = {
  parser: "postcss-safe-parser",
  plugins: {
    "postcss-import": {},
    "postcss-cssnext": {
      features: {
        customProperties: {
          preserve: true
        }
      }
    },
    "cssnano":  {
      discardComments: {
        removeAll: true
      }
    }
  }
};

module.exports = postcssConfig;