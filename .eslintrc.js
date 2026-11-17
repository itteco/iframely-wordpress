module.exports = {
  root: true,
  extends: ['plugin:@wordpress/eslint-plugin/recommended'],
  env: {
    browser: true,
  },
  globals: {
    // Provided at runtime by https://if-cdn.com/embed.js and options.js.
    iframely: 'readonly',
    // WordPress bundles jQuery; the editor script uses it for iframe data storage.
    jQuery: 'readonly',
  },
  overrides: [
    {
      files: ['tests/js/**/*.js'],
      extends: ['plugin:@wordpress/eslint-plugin/test-unit'],
    },
    {
      files: ['tests/e2e/**/*.js', 'playwright.config.js'],
      extends: ['plugin:@wordpress/eslint-plugin/test-playwright'],
    },
  ],
};
