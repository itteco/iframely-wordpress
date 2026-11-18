const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const { VALID_KEY, OTHER_VALID_KEY, REJECTED_KEY, resetPlugin, apiRequests } = require('./fixtures/iframely');

const SETTINGS_PAGE = ['options-general.php', 'page=iframely'];

test.describe('Iframely settings', () => {
  test.describe('without an API key', () => {
    test.beforeEach(async ({ requestUtils }) => {
      await resetPlugin(requestUtils);
    });

    test('the plugin is active and asks for an API key', async ({ admin, page }) => {
      await admin.visitAdminPage('plugins.php');

      await expect(page.locator('tr[data-plugin="iframely/iframely.php"]')).toHaveClass(/\bactive\b/);
      const reminder = page.locator('.notice', { hasText: 'Complete Iframely installation.' });
      await expect(reminder).toBeVisible();

      await reminder.getByRole('link', { name: 'Enter your API key' }).click();
      await expect(page.getByRole('heading', { level: 1, name: 'Activate Iframely' })).toBeVisible();
    });

    test('a malformed API key is refused', async ({ admin, page, requestUtils }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      await page.locator('#iframely_api_key').fill('not a key!');
      await page.getByRole('button', { name: 'Connect with API key' }).click();

      await expect(page.locator('.notice-error')).toContainText('Invalid API key');
      await expect(page.getByRole('heading', { level: 1, name: 'Activate Iframely' })).toBeVisible();
      expect(await apiRequests(requestUtils)).toEqual([]);
    });

    test('an API key that Iframely rejects is refused', async ({ admin, page }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      await page.locator('#iframely_api_key').fill(REJECTED_KEY);
      await page.getByRole('button', { name: 'Connect with API key' }).click();

      await expect(page.locator('.notice-error')).toContainText('be verified (error code 403)');
      await expect(page.getByRole('heading', { level: 1, name: 'Activate Iframely' })).toBeVisible();
    });

    test('a valid API key activates the plugin and shows the onboarding', async ({ admin, page, requestUtils }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      await page.locator('#iframely_api_key').fill(VALID_KEY);
      const saved = page.waitForResponse((response) => response.request().method() === 'POST');
      await page.getByRole('button', { name: 'Connect with API key' }).click();

      // The onboarding is part of the page, not printed in front of it.
      expect((await (await saved).text()).trimStart()).toMatch(/^<!DOCTYPE html>/i);
      await expect(page.locator('#wpbody-content .iframely-onboarding')).toContainText('Enjoy your new embeds!');
      await expect(page.getByRole('heading', { level: 1, name: 'Iframely – media embed blocks' })).toBeVisible();
      await expect(page.locator('#iframely_api_key')).toHaveValue(VALID_KEY);
      await expect(page.locator('#iframely_api_key')).toBeDisabled();

      const requests = await apiRequests(requestUtils);
      expect(requests).toHaveLength(1);
      expect(requests[0]).toMatchObject({ api_key: VALID_KEY, origin: 'localhost:8889' });

      // The reminder is gone.
      await admin.visitAdminPage('plugins.php');
      await expect(page.locator('.notice', { hasText: 'Complete Iframely installation.' })).toBeHidden();
    });
  });

  test.describe('with an API key', () => {
    test.beforeEach(async ({ requestUtils }) => {
      await resetPlugin(requestUtils, VALID_KEY);
    });

    test('a first activation comes with the documented defaults', async ({ admin, page }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);

      await expect(page.locator('#iframely_builtins_replace')).toBeChecked();
      await expect(page.locator('#iframely_previews_enhance')).toBeChecked();
      await expect(page.locator('#iframely_cache_refresh')).toBeChecked();
      await expect(page.locator('#iframely_cache_ttl')).toHaveValue('604800');
      await expect(page.locator('#iframely_api_params')).toHaveValue('');
    });

    test('settings are saved and shown again', async ({ admin, page }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      await page.locator('#iframely_builtins_replace').uncheck();
      await page.locator('#iframely_previews_enhance').uncheck();
      await page.locator('#iframely_cache_ttl').selectOption({ label: '3 days' });
      await page.locator('#iframely_api_params').fill('iframe=card&theme=dark');
      await page.getByRole('button', { name: 'Save Changes' }).click();

      await expect(page.locator('.notice-success')).toContainText('Settings saved.');

      await admin.visitAdminPage(...SETTINGS_PAGE);
      await expect(page.locator('#iframely_builtins_replace')).not.toBeChecked();
      await expect(page.locator('#iframely_previews_enhance')).not.toBeChecked();
      await expect(page.locator('#iframely_cache_refresh')).toBeChecked();
      await expect(page.locator('#iframely_cache_ttl')).toHaveValue('259200');
      await expect(page.locator('#iframely_api_params')).toHaveValue('iframe=card&theme=dark');
      await expect(page.locator('#iframely_api_key')).toHaveValue(VALID_KEY);
    });

    test('the tabs switch between settings, customization and help', async ({ admin, page }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      const tabs = page.locator('.nav-tab-wrapper');
      await expect(tabs.locator('.nav-tab-active')).toHaveText('Enable');

      await tabs.getByRole('link', { name: 'Customize' }).click();
      await expect(tabs.locator('.nav-tab-active')).toHaveText('Customize');
      await expect(page.locator('.iframely-customize')).toBeVisible();
      await expect(page.locator('#iframely_cache_refresh')).toBeHidden();

      await tabs.getByRole('link', { name: 'Help & Support' }).click();
      await expect(tabs.locator('.nav-tab-active')).toHaveText('Help & Support');
      await expect(page.locator('#iframely_cache_refresh')).toBeHidden();
    });

    test('the API key can be replaced after a confirmation', async ({ admin, page }) => {
      await admin.visitAdminPage(...SETTINGS_PAGE);
      await page.getByRole('link', { name: 'Change API key' }).click();

      // Confirmation step.
      await expect(page.locator('#iframely_reactivation_request')).toBeAttached();
      await page.getByRole('button', { name: 'Change API key' }).click();

      await expect(page.locator('.notice')).toContainText(['Please enter new API key.']);
      await expect(page.getByRole('heading', { level: 1, name: 'Activate Iframely' })).toBeVisible();

      // A rejected key leaves the old one in place.
      await page.locator('#iframely_api_key').fill(REJECTED_KEY);
      await page.getByRole('button', { name: 'Connect with API key' }).click();
      await expect(page.locator('.notice-error')).toContainText('be verified');

      await page.locator('#iframely_api_key').fill(OTHER_VALID_KEY);
      await page.getByRole('button', { name: 'Connect with API key' }).click();

      await expect(page.locator('.notice-success')).toContainText('API key has been updated.');
      await expect(page.locator('#iframely_api_key')).toHaveValue(OTHER_VALID_KEY);
    });
  });
});
