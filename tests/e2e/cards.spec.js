const { test, expect } = require('@wordpress/e2e-test-utils-playwright');
const {
  VALID_KEY,
  stubIframelyCdn,
  resetPlugin,
  apiRequests,
  failNextApiRequests,
  delayNextApiRequest,
  insertEmbed,
  changeOption,
  iframelyParam,
} = require('./fixtures/iframely');

// A page without rich media. The API stub answers with a summary card for it, as
// the real API does, and with the one option the real card has.
const CARD_URL = 'https://en.wikipedia.org/wiki/Tiramisu';
const SMALL_CARD = /[?&]card=small/;
const REGULAR_CARD = /^(?!.*[?&]card=)/;
const SMALL_CARD_URL = `${CARD_URL}?${iframelyParam({ card: 'small' })}`;

test.describe('Iframely cards', () => {
  test.beforeEach(async ({ context, requestUtils }) => {
    await stubIframelyCdn(context);
    await resetPlugin(requestUtils, VALID_KEY);
  });

  test.afterAll(async ({ requestUtils }) => {
    await requestUtils.deleteAllPosts();
  });

  /**
   * Starts a post with a card in it, selected and with its options open.
   *
   * @param {Object}                                                fixtures        Playwright fixtures of the test.
   * @param {import('@wordpress/e2e-test-utils-playwright').Admin}  fixtures.admin
   * @param {import('@wordpress/e2e-test-utils-playwright').Editor} fixtures.editor
   * @param {string}                                                title
   */
  async function startPostWithCard({ admin, editor }, title) {
    await admin.createNewPost({ title });
    const { block, preview } = await insertEmbed(editor, CARD_URL);
    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'regular');
    await editor.selectBlocks(block);
    await editor.openDocumentSettingsSidebar();
    return { block, preview };
  }

  test('a page without rich media is previewed as a card', async ({ admin, editor, requestUtils }) => {
    await admin.createNewPost();
    const { preview } = await insertEmbed(editor, CARD_URL);

    const card = preview.locator('.iframely-e2e-embed');
    await expect(card).toHaveAttribute('data-url', CARD_URL);
    await expect(card).toHaveAttribute('data-kind', 'card');
    await expect(card).toHaveAttribute('data-size', 'regular');

    const requests = await apiRequests(requestUtils);
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({ url: CARD_URL, api_key: VALID_KEY, gutenberg: '2', iframe: '1' });
    expect(requests[0]).not.toHaveProperty('card');
  });

  test('a selected card offers to be made smaller', async ({ admin, editor, page }) => {
    await startPostWithCard({ admin, editor }, 'Card options');

    await expect(page.getByRole('button', { name: 'Iframely options' })).toBeVisible();
    await expect(page.getByLabel('Make it a small card')).not.toBeChecked();
    // A card has none of the options of a player.
    await expect(page.getByLabel('Closed captions')).toHaveCount(0);
  });

  test('a small card is previewed, saved and used on the front end', async ({ admin, editor, page, requestUtils }) => {
    const { preview } = await startPostWithCard({ admin, editor }, 'Small card');

    await changeOption(page, preview, SMALL_CARD, () => page.getByLabel('Make it a small card').check());

    // The preview is the small card, the form shows what the card reports back,
    // and the block has the choice in its URL.
    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'small');
    await expect(page.getByLabel('Make it a small card')).toBeChecked();
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.url).toBe(SMALL_CARD_URL);

    // The option travels in the saved URL.
    const postId = await editor.publishPost();
    const content = await editor.getEditedPostContent();
    expect(content).toContain(`${CARD_URL}?${iframelyParam({ card: 'small' })}`);

    // And reaches the API when the post is rendered.
    await page.goto(`/?p=${postId}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toHaveAttribute('data-size', 'small');
    await expect(rendered).toHaveAttribute('data-url', CARD_URL);
    await expect(rendered.locator('iframe')).toHaveCSS('height', '140px');
    const query = JSON.parse(await rendered.getAttribute('data-query'));
    expect(query).toMatchObject({ card: 'small', api_key: VALID_KEY });
    expect(query).not.toHaveProperty('gutenberg');

    const requests = await apiRequests(requestUtils);
    expect(requests.filter((request) => !request.gutenberg)).toHaveLength(1);
  });

  test('clearing the option brings the regular card back', async ({ admin, editor, page }) => {
    const { preview } = await startPostWithCard({ admin, editor }, 'Card made small and regular again');
    await changeOption(page, preview, SMALL_CARD, () => page.getByLabel('Make it a small card').check());
    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'small');

    await changeOption(page, preview, REGULAR_CARD, () => page.getByLabel('Make it a small card').uncheck());

    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'regular');
    await expect(page.getByLabel('Make it a small card')).not.toBeChecked();
    const [embed] = await editor.getBlocks();
    expect(embed.attributes.url).toBe(CARD_URL);

    // Nothing of the option is left in the saved URL or on the front end.
    const postId = await editor.publishPost();
    const content = await editor.getEditedPostContent();
    expect(content).toContain(CARD_URL);
    expect(content).not.toContain('iframely=');

    await page.goto(`/?p=${postId}`);
    const rendered = page.locator('.iframely-e2e-embed');
    await expect(rendered).toHaveAttribute('data-size', 'regular');
    await expect(rendered.locator('iframe')).toHaveCSS('height', '320px');
    expect(JSON.parse(await rendered.getAttribute('data-query'))).not.toHaveProperty('card');
  });

  test('a card can be switched between small and regular again and again', async ({ admin, editor, page }) => {
    const { preview } = await startPostWithCard({ admin, editor }, 'Card switched back and forth');
    const option = page.getByLabel('Make it a small card');
    const card = preview.locator('.iframely-e2e-embed');
    const blockUrl = async () => (await editor.getBlocks())[0].attributes.url;

    for (let round = 1; round <= 4; round++) {
      await changeOption(page, preview, SMALL_CARD, () => option.check());
      await expect(card, `round ${round}`).toHaveAttribute('data-size', 'small');
      await expect(option, `round ${round}`).toBeChecked();
      expect(await blockUrl(), `round ${round}`).toBe(SMALL_CARD_URL);

      await changeOption(page, preview, REGULAR_CARD, () => option.uncheck());
      await expect(card, `round ${round}`).toHaveAttribute('data-size', 'regular');
      await expect(option, `round ${round}`).not.toBeChecked();
      expect(await blockUrl(), `round ${round}`).toBe(CARD_URL);
    }

    // The block is still in order after all that.
    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);
    await changeOption(page, preview, SMALL_CARD, () => option.check());
    await editor.publishPost();
    expect(await editor.getEditedPostContent()).toContain(SMALL_CARD_URL);
  });

  for (const clicks of [2, 5, 6]) {
    test(`${clicks} quick clicks on the option end in the state of the last click`, async ({ admin, editor, page }) => {
      const { preview } = await startPostWithCard({ admin, editor }, 'Card option clicked quickly');
      const option = page.getByLabel('Make it a small card');
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));

      // The clicks do not wait for the preview, not even for the first small card.
      for (let click = 1; click <= clicks; click++) {
        await option.click();
      }

      const small = clicks % 2 === 1;
      await expect(async () => {
        await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', small ? 'small' : 'regular');
        await expect(option).toBeChecked({ checked: small });
        const [embed] = await editor.getBlocks();
        expect(embed.attributes.url).toBe(small ? SMALL_CARD_URL : CARD_URL);
      }).toPass();
      await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);
      expect(errors).toEqual([]);

      // And the panel still works.
      await changeOption(page, preview, small ? REGULAR_CARD : SMALL_CARD, () => option.click());
      await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', small ? 'regular' : 'small');
    });
  }

  /*
   * The first time an option is used, the editor has to load a new preview. With
   * the real API that takes from a third of a second to a few seconds, and the
   * panel stays on screen all that time.
   */
  test('a click made while a new preview loads counts', async ({ admin, editor, page, requestUtils }) => {
    const { preview } = await startPostWithCard({ admin, editor }, 'Card option clicked twice');
    const option = page.getByLabel('Make it a small card');

    await delayNextApiRequest(requestUtils, 1500);
    const lateAnswer = page.waitForResponse((response) => decodeURIComponent(response.url()).includes('iframely='));
    await option.click();
    // The user changes their mind before the small card is there.
    await option.click();
    await expect(option).not.toBeChecked();

    await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'regular');
    // The late answer for the small card changes nothing.
    await lateAnswer;
    await expect(async () => {
      await expect(preview.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'regular');
      await expect(option).not.toBeChecked();
      const [embed] = await editor.getBlocks();
      expect(embed.attributes.url).toBe(CARD_URL);
    }).toPass();
  });

  /*
   * Seen with the real API: one preview request took longer than the five seconds
   * WordPress waits, and the block said "Sorry, this content could not be embedded".
   */
  test.describe('when the preview fails after an option change', () => {
    async function startWithFailedSmallCard({ admin, editor, page, requestUtils }) {
      const { block } = await startPostWithCard({ admin, editor }, 'Card whose small preview fails');
      await failNextApiRequests(requestUtils, 1);
      await page.getByLabel('Make it a small card').check();
      await expect(block.getByText('Sorry, this content could not be embedded.')).toBeVisible();
      return block;
    }

    const cardIn = (block) => block.locator('iframe').contentFrame().locator('.iframely-e2e-embed');

    test('the options panel takes the change back', async ({ admin, editor, page, requestUtils }) => {
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      const block = await startWithFailedSmallCard({ admin, editor, page, requestUtils });

      await page.getByLabel('Make it a small card').uncheck();

      await expect(cardIn(block)).toHaveAttribute('data-size', 'regular');
      await expect(page.getByLabel('Make it a small card')).not.toBeChecked();
      const [embed] = await editor.getBlocks();
      expect(embed.attributes.url).toBe(CARD_URL);
      expect(errors).toEqual([]);
    });

    test('the choice is kept, and the post shows it when opened again', async ({
      admin,
      editor,
      page,
      requestUtils,
    }) => {
      await startWithFailedSmallCard({ admin, editor, page, requestUtils });
      const [embed] = await editor.getBlocks();
      expect(embed.attributes.url).toBe(SMALL_CARD_URL);

      const postId = await editor.publishPost();
      await admin.editPost(postId);

      await expect(cardIn(editor.canvas.locator('[data-type="core/embed"]'))).toHaveAttribute('data-size', 'small');
    });

    // The block of WordPress retries the URL it was first shown with. It has to be
    // the URL with the options, on every supported version of WordPress.
    test('"Try again" brings the preview back', async ({ admin, editor, page, requestUtils }) => {
      const block = await startWithFailedSmallCard({ admin, editor, page, requestUtils });
      await expect(block.locator('input[type="url"]')).toHaveValue(SMALL_CARD_URL);

      await block.getByRole('button', { name: 'Try again' }).click();

      await expect(cardIn(block)).toHaveAttribute('data-size', 'small');
      await expect(page.getByLabel('Make it a small card')).toBeChecked();
    });
  });

  test('a small card is still small and valid when the post is opened again', async ({ admin, editor, page }) => {
    const { preview } = await startPostWithCard({ admin, editor }, 'Small card reopened');
    await changeOption(page, preview, SMALL_CARD, () => page.getByLabel('Make it a small card').check());
    const postId = await editor.publishPost();

    await admin.editPost(postId);

    const reopened = editor.canvas.locator('[data-type="core/embed"]');
    const card = reopened.locator('iframe').contentFrame().locator('.iframely-e2e-embed');
    await expect(card).toHaveAttribute('data-size', 'small');
    await expect(editor.canvas.locator('.block-editor-warning')).toHaveCount(0);

    await editor.selectBlocks(reopened);
    await editor.openDocumentSettingsSidebar();
    await expect(page.getByLabel('Make it a small card')).toBeChecked();
  });

  test('a page URL on its own line becomes a card on the front end', async ({ page, requestUtils }) => {
    const post = await requestUtils.createPost({
      title: 'Plain page URL',
      status: 'publish',
      content: `Before\n\n${CARD_URL}\n\nAfter`,
    });

    await page.goto(`/?p=${post.id}`);

    const card = page.locator('.iframely-e2e-embed');
    await expect(card).toHaveAttribute('data-kind', 'card');
    await expect(card).toHaveAttribute('data-size', 'regular');
    await expect(card.locator('iframe').contentFrame().locator('.iframely-e2e-frame')).toContainText(CARD_URL);
  });

  test('small cards can be made the default for the whole site', async ({ page, requestUtils }) => {
    await page.goto('/wp-admin/options-general.php?page=iframely');
    await page.locator('#iframely_api_params').fill('iframe=card-small');
    await page.getByRole('button', { name: 'Save Changes' }).click();
    await expect(page.locator('.notice-success')).toContainText('Settings saved.');

    const post = await requestUtils.createPost({
      title: 'Card with site-wide settings',
      status: 'publish',
      content: CARD_URL,
    });
    await page.goto(`/?p=${post.id}`);

    await expect(page.locator('.iframely-e2e-embed')).toHaveAttribute('data-size', 'small');
    // The shorthand from the settings is spelled out for the API.
    const [request] = await apiRequests(requestUtils);
    expect(request).toMatchObject({ url: CARD_URL, iframe: '1', card: 'small' });
  });

  test.describe('links to posts of this site', () => {
    let target;

    test.beforeEach(async ({ requestUtils }) => {
      target = await requestUtils.createPost({ title: 'Linked post', status: 'publish', content: 'Hello.' });
    });

    test('become Iframely cards by default', async ({ page, requestUtils }) => {
      const post = await requestUtils.createPost({
        title: 'Links to own post',
        status: 'publish',
        content: target.link,
      });

      await page.goto(`/?p=${post.id}`);

      const card = page.locator('.iframely-e2e-embed');
      await expect(card).toHaveAttribute('data-kind', 'card');
      await expect(card).toHaveAttribute('data-url', target.link);
      await expect(page.locator('.wp-embedded-content')).toHaveCount(0);
    });

    test('keep the WordPress preview when site previews are not enhanced', async ({ page, requestUtils }) => {
      await page.goto('/wp-admin/options-general.php?page=iframely');
      await page.locator('#iframely_previews_enhance').uncheck();
      await page.getByRole('button', { name: 'Save Changes' }).click();
      await expect(page.locator('.notice-success')).toContainText('Settings saved.');

      const post = await requestUtils.createPost({
        title: 'Links to own post',
        status: 'publish',
        content: target.link,
      });
      await page.goto(`/?p=${post.id}`);

      await expect(page.locator('iframe.wp-embedded-content')).toBeAttached();
      await expect(page.locator('.iframely-e2e-embed')).toHaveCount(0);
      expect(await apiRequests(requestUtils)).toHaveLength(0);
    });
  });
});
