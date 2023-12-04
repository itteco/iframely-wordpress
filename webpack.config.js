const defaultConfig = require('@wordpress/scripts/config/webpack.config');

const { getWebpackEntryPoints } = require('@wordpress/scripts/utils/config');

module.exports = {
  ...defaultConfig,
  entry: {
    ...getWebpackEntryPoints(),
    admin: './src/admin.js',
  },
};
