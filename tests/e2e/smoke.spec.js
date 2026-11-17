const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Proves the Playwright harness works: the tests site is reachable, the admin
 * session is valid and the plugin is active.
 */
test.describe('Iframely plugin', () => {
  test('is listed as active on the Plugins screen', async ({ admin, page }) => {
    await admin.visitAdminPage('plugins.php');

    await expect(page.locator('tr[data-plugin="iframely/iframely.php"]')).toHaveClass(/\bactive\b/);
  });

  test('registers its settings page', async ({ admin, page }) => {
    await admin.visitAdminPage('options-general.php', 'page=iframely');

    await expect(page.locator('.wrap h1')).toBeVisible();
  });
});
