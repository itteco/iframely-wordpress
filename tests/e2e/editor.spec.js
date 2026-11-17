const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const {
  VALID_KEY,
  stubIframelyCdn,
  resetPlugin,
  apiRequests,
  delayNextApiRequest,
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
    await changeOption(page, preview, /[?&]_cc_load_policy=true/, () => page.getByLabel('Closed captions').check());
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

  test('options of a video can be changed again and again', async ({ admin, editor, page }) => {
    await admin.createNewPost({ title: 'Video with changing options' });
    const { block, preview } = await insertEmbed(editor, VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    const captions = page.getByLabel('Closed captions');
    const start = page.getByLabel('Start from');
    const startFrom = (value) => async () => {
      await start.fill(value);
      await start.blur();
    };
    // What the user does, what the address of the embed then looks like, and the
    // options the block should hold (in the order of the form). The last steps
    // return to combinations that were used before.
    const steps = [
      [() => captions.check(), /^(?=.*_cc_load_policy=true)(?!.*_start=)/, { _cc_load_policy: true }],
      [startFrom('1m10s'), /^(?=.*_cc_load_policy=true)(?=.*_start=1m10s)/, { _start: '1m10s', _cc_load_policy: true }],
      [() => captions.uncheck(), /^(?!.*_cc_load_policy)(?=.*_start=1m10s)/, { _start: '1m10s' }],
      [startFrom('30'), /^(?!.*_cc_load_policy)(?=.*_start=30)/, { _start: '30' }],
      [() => captions.check(), /^(?=.*_cc_load_policy=true)(?=.*_start=30)/, { _start: '30', _cc_load_policy: true }],
      [startFrom(''), /^(?=.*_cc_load_policy=true)(?!.*_start=)/, { _cc_load_policy: true }],
      [() => captions.uncheck(), /^(?!.*_cc_load_policy)(?!.*_start=)/, {}],
      [() => captions.check(), /^(?=.*_cc_load_policy=true)(?!.*_start=)/, { _cc_load_policy: true }],
      [startFrom('1m10s'), /^(?=.*_cc_load_policy=true)(?=.*_start=1m10s)/, { _start: '1m10s', _cc_load_policy: true }],
    ];

    for (const [index, [change, src, options]] of steps.entries()) {
      const step = `step ${index + 1}`;
      await changeOption(page, preview, src, change);

      await expect(captions, step).toBeChecked({ checked: Boolean(options._cc_load_policy) });
      await expect(start, step).toHaveValue(options._start || '');
      const [embed] = await editor.getBlocks();
      expect(embed.attributes.url, step).toBe(
        Object.keys(options).length ? `${VIDEO_URL}&${iframelyParam(options)}` : VIDEO_URL,
      );
    }

    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);
    await editor.publishPost();
    // In the saved HTML the "&" of the URL is written as an entity, as WordPress does for any embed.
    expect(await editor.getEditedPostContent()).toContain(`${VIDEO_URL}&amp;${iframelyParam(VIDEO_OPTIONS)}`);
  });

  test('an option changed while the preview of another change loads is applied too', async ({
    admin,
    editor,
    page,
    requestUtils,
  }) => {
    await admin.createNewPost({ title: 'Video with two quick changes' });
    const { block, preview } = await insertEmbed(editor, VIDEO_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toBeVisible();
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();

    await delayNextApiRequest(requestUtils, 1500);
    await page.getByLabel('Closed captions').check();
    // The preview with the captions is not there yet.
    await page.getByLabel('Start from').fill('1m10s');
    await page.getByLabel('Start from').blur();

    const frame = preview.locator('iframe[title="Iframely stub embed"]');
    await expect(frame).toHaveAttribute('src', /^(?=.*_cc_load_policy=true)(?=.*_start=1m10s)/);
    await expect(page.getByLabel('Closed captions')).toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('1m10s');
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.url).toBe(`${VIDEO_URL}&${iframelyParam(VIDEO_OPTIONS)}`);
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
    // reports back, and the block has the choice in its URL.
    const frame = preview.locator('iframe[title="Iframely stub embed"]');
    await expect(frame).toHaveAttribute('src', /[?&]_cc_load_policy=true/);
    await expect(frame).toHaveAttribute('src', /[?&]_start=1m10s/);
    await expect(page.getByLabel('Closed captions')).toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('1m10s');
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.url).toBe(`${VIDEO_URL}&${iframelyParam(VIDEO_OPTIONS)}`);

    // The options travel in the saved URL.
    const postId = await editor.publishPost();
    const content = await editor.getEditedPostContent();
    expect(content).toContain(`${VIDEO_URL}&amp;${iframelyParam(VIDEO_OPTIONS)}`);

    // And reach the API when the post is rendered.
    await page.goto(`/?p=${postId}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toBeVisible();
    const query = JSON.parse(await rendered.getAttribute('data-query'));
    expect(query).toMatchObject({ _cc_load_policy: 'true', _start: '1m10s', api_key: VALID_KEY });
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

  test('a post saved by an earlier version of the plugin opens in order and keeps its options', async ({
    admin,
    editor,
    page,
    requestUtils,
  }) => {
    // Earlier versions wrote the "&" of the URL as it is, and kept a copy of the
    // options in an attribute of their own.
    const url = `${VIDEO_URL}&${iframelyParam(VIDEO_OPTIONS)}`;
    const attributes = JSON.stringify({ url, iquery: VIDEO_OPTIONS }).replace(/&/g, '\\u0026');
    const post = await requestUtils.createPost({
      title: 'Saved by an earlier version',
      status: 'publish',
      content:
        `<!-- wp:embed ${attributes} -->\n` +
        `<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">\n${url}\n</div></figure>\n` +
        '<!-- /wp:embed -->',
    });

    await admin.editPost(post.id);

    const block = editor.canvas.locator('[data-type="core/embed"]');
    const frame = block.locator('iframe').contentFrame().locator('iframe[title="Iframely stub embed"]');
    await expect(frame).toHaveAttribute('src', /^(?=.*_cc_load_policy=true)(?=.*_start=1m10s)/);
    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();
    await expect(page.getByLabel('Closed captions')).toBeChecked();
    await expect(page.getByLabel('Start from')).toHaveValue('1m10s');

    // An option can be changed, and the copy of the options is not written any more.
    await changeOption(page, block.locator('iframe').contentFrame(), /^(?!.*_cc_load_policy=)(?=.*_start=1m10s)/, () =>
      page.getByLabel('Closed captions').uncheck(),
    );
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.url).toBe(`${VIDEO_URL}&${iframelyParam({ _start: '1m10s' })}`);
    expect(embed.attributes).not.toHaveProperty('iquery');
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
