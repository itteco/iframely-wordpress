const path = require('path');
const { defineConfig } = require('@playwright/test');

// The end-to-end tests run against the wp-env tests site (.wp-env.tests.json).
process.env.WP_BASE_URL ??= 'http://localhost:8889';
process.env.WP_ARTIFACTS_PATH ??= path.join(__dirname, 'artifacts');

const baseConfig = require('@wordpress/scripts/config/playwright.config');

module.exports = defineConfig({
  ...baseConfig,
  testDir: './tests/e2e',
  webServer: {
    command: 'npm run env:start:tests',
    port: 8889,
    timeout: 180_000,
    reuseExistingServer: true,
  },
});
