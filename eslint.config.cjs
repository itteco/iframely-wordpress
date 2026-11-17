const wpPlugin = require('@wordpress/eslint-plugin');

const scoped = (configs, files) => configs.map((config) => ({ ...config, files }));

module.exports = [
  {
    ignores: ['build/**', 'node_modules/**', 'vendor/**', 'artifacts/**', 'playwright-report/**', 'test-results/**'],
  },
  ...wpPlugin.configs.recommended,
  {
    languageOptions: {
      globals: {
        // Provided at runtime by https://if-cdn.com/embed.js and options.js.
        iframely: 'readonly',
        // WordPress bundles jQuery; the editor script uses it for iframe data storage.
        jQuery: 'readonly',
      },
      parserOptions: {
        requireConfigFile: false,
        babelOptions: {
          presets: [require.resolve('@wordpress/babel-preset-default')],
        },
      },
    },
  },
  ...scoped(wpPlugin.configs['test-unit'], ['tests/js/**/*.js']),
  ...scoped(wpPlugin.configs['test-playwright'], ['tests/e2e/**/*.js', 'playwright.config.js']),
  {
    // Command-line helpers.
    files: ['tests/bin/**/*.js', 'tools/**/*.js'],
    rules: { 'no-console': 'off' },
  },
];
