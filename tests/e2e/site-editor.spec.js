const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const { VALID_KEY, stubIframelyCdn, resetPlugin, apiRequests } = require('./fixtures/iframely');

const ARTICLE_URL = 'https://example.com/some/article';

test.describe('Iframely embeds in the site editor', () => {
  test.beforeEach(async ({ context, requestUtils }) => {
    await stubIframelyCdn(context);
    await resetPlugin(requestUtils, VALID_KEY);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deleteAllPages();
  });

  test('a page edited in the site editor gets a preview, the options and a working embed', async ({
    admin,
    editor,
    page,
    requestUtils,
  }) => {
    // An empty page would open the starter pattern chooser first.
    const { id } = await requestUtils.createPage({
      title: 'Site editor page',
      status: 'publish',
      content: '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->',
    });
    await admin.visitSiteEditor({ postId: id, postType: 'page', canvas: 'edit' });
    // Blocks inserted before the page content has loaded are discarded.
    await expect(editor.canvas.getByText('Intro')).toBeVisible();

    // Older site editors show the page inside its template, where new blocks belong to the content block.
    const contentBlock = await page.evaluate(
      () => window.wp.data.select('core/block-editor').getBlocksByName('core/post-content')[0],
    );
    await editor.insertBlock({ name: 'core/embed', attributes: { url: ARTICLE_URL } }, { clientId: contentBlock });
    const block = editor.canvas.locator('[data-type="core/embed"]');
    const preview = block.locator('iframe').contentFrame();
    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', ARTICLE_URL);

    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();
    await expect(page.getByRole('button', { name: 'Iframely options' })).toBeVisible();
    await page.locator('#iframely-options input[name="theme"]').check();
    await expect(preview.locator('iframe[title="Iframely stub embed"]')).toHaveAttribute('src', /[?&]theme=dark/);

    // With a single changed entity the site editor saves without a confirmation panel.
    await page
      .getByRole('region', { name: 'Editor top bar' })
      .getByRole('button', { name: 'Save', exact: true })
      .click();
    await expect.poll(() => page.evaluate(() => window.wp.data.select('core/editor').isEditedPostDirty())).toBe(false);

    await page.goto(`/?page_id=${id}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toBeVisible();
    const query = JSON.parse(await rendered.getAttribute('data-query'));
    expect(query).toMatchObject({ theme: 'dark', api_key: VALID_KEY });

    const requests = await apiRequests(requestUtils);
    expect(requests.filter((request) => !request.gutenberg)).toHaveLength(1);
  });
});
