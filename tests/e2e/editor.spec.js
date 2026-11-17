const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const { VALID_KEY, stubIframelyCdn, resetPlugin, apiRequests } = require('./fixtures/iframely');

const ARTICLE_URL = 'https://example.com/some/article';
const YOUTUBE_URL = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

test.describe('Iframely embeds', () => {
  test.beforeEach(async ({ context, requestUtils }) => {
    await stubIframelyCdn(context);
    await resetPlugin(requestUtils, VALID_KEY);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deleteAllPosts();
  });

  /**
   * Inserts an embed block and returns locators for it and for its preview.
   *
   * @param {import('@wordpress/e2e-test-utils-playwright').Editor} editor
   * @param {string}                                                url
   */
  async function insertEmbed(editor, url) {
    await editor.insertBlock({ name: 'core/embed', attributes: { url } });
    const block = editor.canvas.locator('[data-type="core/embed"]');
    const preview = block.locator('iframe').contentFrame();
    return { block, preview };
  }

  test('the editor previews an embed through Iframely', async ({ admin, editor, requestUtils }) => {
    await admin.createNewPost();
    const { preview } = await insertEmbed(editor, ARTICLE_URL);

    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', ARTICLE_URL);

    const requests = await apiRequests(requestUtils);
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({
      url: ARTICLE_URL,
      api_key: VALID_KEY,
      origin: 'localhost:8889',
      // Marks a request made for the editor preview.
      gutenberg: '2',
      iframe: '1',
    });
  });

  test('Iframely replaces the built-in providers by default', async ({ admin, editor }) => {
    await admin.createNewPost();
    const { preview } = await insertEmbed(editor, YOUTUBE_URL);

    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', YOUTUBE_URL);
  });

  test('a selected embed shows the Iframely options panel', async ({ admin, editor, page }) => {
    await admin.createNewPost();
    const { block, preview } = await insertEmbed(editor, ARTICLE_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();

    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    await expect(page.getByRole('button', { name: 'Iframely options' })).toBeVisible();
    await expect(page.locator('#iframely-options')).toContainText('Dark theme (stub)');
  });

  test('an option chosen in the panel is applied, saved and used on the front end', async ({
    admin,
    editor,
    page,
    requestUtils,
  }) => {
    await admin.createNewPost({ title: 'Embed with options' });
    const { block, preview } = await insertEmbed(editor, ARTICLE_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    await page.locator('#iframely-options input[name="theme"]').check();

    // The preview reloads with the option and the block remembers it.
    await expect(preview.locator('iframe[title="Iframely stub embed"]')).toHaveAttribute('src', /[?&]theme=dark/);
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.iquery).toEqual({ theme: 'dark' });

    // The option travels in the saved URL.
    const postId = await editor.publishPost();
    const content = await editor.getEditedPostContent();
    expect(content).toContain(`${ARTICLE_URL}?iframely=${encodeURIComponent(btoa('{"theme":"dark"}'))}`);

    // And reaches the API when the post is rendered.
    await page.goto(`/?p=${postId}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toBeVisible();
    const query = JSON.parse(await rendered.getAttribute('data-query'));
    expect(query).toMatchObject({ theme: 'dark', api_key: VALID_KEY });
    expect(query.url.startsWith(ARTICLE_URL)).toBe(true);
    expect(query).not.toHaveProperty('gutenberg');

    const requests = await apiRequests(requestUtils);
    expect(requests.filter((request) => !request.gutenberg)).toHaveLength(1);
  });

  test('a saved embed is still valid when the post is opened again', async ({ admin, editor, page }) => {
    await admin.createNewPost({ title: 'Embed reopened' });
    const { block, preview } = await insertEmbed(editor, ARTICLE_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();
    await page.locator('#iframely-options input[name="theme"]').check();
    await expect(preview.locator('iframe[title="Iframely stub embed"]')).toHaveAttribute('src', /[?&]theme=dark/);
    const postId = await editor.publishPost();

    await admin.editPost(postId);

    const reopened = editor.canvas.locator('[data-type="core/embed"]');
    await expect(reopened.locator('iframe').contentFrame().locator('.iframely-e2e-embed')).toBeVisible();
    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);
  });

  test('a plain URL on its own line is embedded on the front end', async ({ page, requestUtils }) => {
    const post = await requestUtils.createPost({
      title: 'Plain URL',
      status: 'publish',
      content: `Before\n\n${ARTICLE_URL}\n\nAfter`,
    });

    await page.goto(`/?p=${post.id}`);

    await expect(page.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', ARTICLE_URL);
    const frame = page.locator('.iframely-e2e-embed iframe').contentFrame();
    await expect(frame.locator('.iframely-e2e-frame')).toContainText(ARTICLE_URL);
  });
});
