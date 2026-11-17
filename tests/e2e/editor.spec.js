const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const {
  VALID_KEY,
  stubIframelyCdn,
  resetPlugin,
  apiRequests,
  insertEmbed,
  changeOption,
  iframelyParam,
} = require('./fixtures/iframely');

// The API stub answers with a player for this URL, as the real API does.
const VIDEO_URL = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';
// In the order of the options form.
const VIDEO_OPTIONS = { _start: '1m10s', _cc_load_policy: true };

test.describe('Iframely rich media embeds', () => {
  test.beforeEach(async ({ context, requestUtils }) => {
    await stubIframelyCdn(context);
    await resetPlugin(requestUtils, VALID_KEY);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deleteAllPosts();
  });

  /**
   * Turns on closed captions and sets a start time in the options panel.
   *
   * @param {import('@playwright/test').Page}         page
   * @param {import('@playwright/test').FrameLocator} preview
   */
  async function chooseVideoOptions(page, preview) {
    await changeOption(page, preview, /[?&]_cc_load_policy=1/, () => page.getByLabel('Closed captions').check());
    await changeOption(page, preview, /[?&]_start=1m10s/, async () => {
      await page.getByLabel('Start from').fill('1m10s');
      await page.getByLabel('Start from').blur();
    });
  }

  test('the editor previews a video through Iframely instead of the built-in provider', async ({
    admin,
    editor,
    requestUtils,
  }) => {
    await admin.createNewPost();
    const { preview } = await insertEmbed(editor, VIDEO_URL);

    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-kind', 'player');

    const requests = await apiRequests(requestUtils);
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({
      url: VIDEO_URL,
      api_key: VALID_KEY,
      origin: 'localhost:8889',
      // Marks a request made for the editor preview.
      gutenberg: '2',
      iframe: '1',
    });
  });

  test('a selected embed shows the options it reports', async ({ admin, editor, page }) => {
    await admin.createNewPost();
    const { block, preview } = await insertEmbed(editor, VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();

    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    await expect(page.getByRole('button', { name: 'Iframely options' })).toBeVisible();
    await expect(page.getByLabel('Closed captions')).not.toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('');
  });

  test('options chosen in the panel are applied, saved and used on the front end', async ({
    admin,
    editor,
    page,
    requestUtils,
  }) => {
    await admin.createNewPost({ title: 'Video with options' });
    const { block, preview } = await insertEmbed(editor, VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    await chooseVideoOptions(page, preview);

    // The preview shows the embed with both options, the form shows what the embed
    // reports back, and the block remembers the choice.
    const frame = preview.locator('iframe[title="Iframely stub embed"]');
    await expect(frame).toHaveAttribute('src', /[?&]_cc_load_policy=1/);
    await expect(frame).toHaveAttribute('src', /[?&]_start=1m10s/);
    await expect(page.getByLabel('Closed captions')).toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('1m10s');
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.iquery).toEqual(VIDEO_OPTIONS);

    // The options travel in the saved URL.
    const postId = await editor.publishPost();
    const content = await editor.getEditedPostContent();
    expect(content).toContain(`${VIDEO_URL}&${iframelyParam(VIDEO_OPTIONS)}`);

    // And reach the API when the post is rendered.
    await page.goto(`/?p=${postId}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toBeVisible();
    const query = JSON.parse(await rendered.getAttribute('data-query'));
    expect(query).toMatchObject({ _cc_load_policy: '1', _start: '1m10s', api_key: VALID_KEY });
    expect(query.url.startsWith(VIDEO_URL)).toBe(true);
    expect(query).not.toHaveProperty('gutenberg');

    const requests = await apiRequests(requestUtils);
    expect(requests.filter((request) => !request.gutenberg)).toHaveLength(1);
  });

  test('a saved embed is still valid and keeps its options when the post is opened again', async ({
    admin,
    editor,
    page,
  }) => {
    await admin.createNewPost({ title: 'Video reopened' });
    const { block, preview } = await insertEmbed(editor, VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();
    await chooseVideoOptions(page, preview);
    const postId = await editor.publishPost();

    await admin.editPost(postId);

    const reopened = editor.canvas.locator('[data-type="core/embed"]');
    await expect(reopened.locator('iframe').contentFrame().locator('.iframely-e2e-embed')).toBeVisible();
    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);

    await editor.selectBlocks(reopened);
    await editor.openDocumentSettingsSidebar();
    await expect(page.getByLabel('Closed captions')).toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('1m10s');
  });

  test('a video URL on its own line is embedded on the front end', async ({ page, requestUtils }) => {
    const post = await requestUtils.createPost({
      title: 'Plain video URL',
      status: 'publish',
      content: `Before\n\n${VIDEO_URL}\n\nAfter`,
    });

    await page.goto(`/?p=${post.id}`);

    await expect(page.locator('.iframely-e2e-embed')).toHaveAttribute('data-url', VIDEO_URL);
    const frame = page.locator('.iframely-e2e-embed iframe').contentFrame();
    await expect(frame.locator('.iframely-e2e-frame')).toContainText(VIDEO_URL);
  });
});
