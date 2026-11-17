<?php

namespace Iframely\Tests;

use Iframely\Options;

/**
 * Summary cards: what Iframely returns for pages without rich media, and the
 * per-URL option that makes a card small.
 *
 * The responses are shaped like the ones the real API gives for the URL below
 * (checked by LiveApiTest).
 */
class CardTest extends TestCase
{
    private const CARD_URL = 'https://en.wikipedia.org/wiki/Tiramisu';

    // What the editor saves when "Make it a small card" is chosen for CARD_URL.
    private const SMALL_CARD_URL = self::CARD_URL . '?iframely=eyJjYXJkIjoic21hbGwifQ%3D%3D';

    private const CARD_HTML = '<div class="iframely-embed"><div class="iframely-responsive" style="padding-bottom: 75%; padding-top: 120px;">'
        . '<a href="https://en.wikipedia.org/wiki/Tiramisu" data-iframely-url="https://iframely.net/api/iframe?url=https%3A%2F%2Fen.wikipedia.org%2Fwiki%2FTiramisu&key=0123456789abcdef0123456789abcdef"></a>'
        . '</div></div><script async src="https://iframely.net/embed.js" charset="utf-8"></script>';

    private const SMALL_CARD_HTML = '<div class="iframely-embed"><div class="iframely-responsive" style="height: 140px; padding-bottom: 0;">'
        . '<a href="https://en.wikipedia.org/wiki/Tiramisu" data-iframely-url="https://iframely.net/api/iframe?card=small&url=https%3A%2F%2Fen.wikipedia.org%2Fwiki%2FTiramisu&key=0123456789abcdef0123456789abcdef"></a>'
        . '</div></div><script async src="https://iframely.net/embed.js" charset="utf-8"></script>';

    /**
     * @var int
     */
    private $postId;

    public function set_up(): void
    {
        parent::set_up();

        $this->postId = self::factory()->post->create();
        $this->enterPost($this->postId);
        $this->respondWithCards();
    }

    public function tear_down(): void
    {
        wp_reset_postdata();

        parent::tear_down();
    }

    /**
     * Answers like the API: a small card when asked for one, a regular card otherwise.
     */
    private function respondWithCards(): void
    {
        $this->respondWith(static function ($url) {
            $query = self::queryOf($url);
            $small = ($query['card'] ?? '') === 'small';
            $options = ['card' => ['value' => $small ? 'small' : '', 'values' => ['small' => 'Make it a small card']]];
            if (isset($query['card'])) {
                $options['query'] = ['card'];
            }

            return self::httpResponse((string)wp_json_encode([
                'version' => '1.0',
                'type' => 'rich',
                'title' => 'Tiramisu',
                'provider_name' => 'Wikipedia',
                'html' => $small ? self::SMALL_CARD_HTML : self::CARD_HTML,
                'options' => $options,
            ]));
        });
    }

    private function render(string $content): string
    {
        return apply_filters('the_content', $content);
    }

    /**
     * Post content is reformatted by WordPress (line breaks, "&" entities), so
     * rendered cards are recognised by their parts instead of the exact markup.
     */
    private function assertRegularCard(string $html): void
    {
        $this->assertStringContainsString('<div class="iframely-responsive" style="padding-bottom: 75%; padding-top: 120px;">', $html);
        $this->assertStringContainsString('<a href="' . self::CARD_URL . '" data-iframely-url="https://iframely.net/api/iframe?url=', $html);
        $this->assertStringContainsString('<script async src="https://iframely.net/embed.js" charset="utf-8"></script>', $html);
    }

    private function assertSmallCard(string $html): void
    {
        $this->assertStringContainsString('<div class="iframely-responsive" style="height: 140px; padding-bottom: 0;">', $html);
        $this->assertStringContainsString('<a href="' . self::CARD_URL . '" data-iframely-url="https://iframely.net/api/iframe?card=small', $html);
        $this->assertStringContainsString('<script async src="https://iframely.net/embed.js" charset="utf-8"></script>', $html);
    }

    public function test_the_small_card_url_is_what_the_editor_script_produces(): void
    {
        $this->assertSame(self::CARD_URL . '?iframely=' . self::encodeUrlOptions(['card' => 'small']), self::SMALL_CARD_URL);
    }

    public function test_a_page_url_on_its_own_line_becomes_a_card(): void
    {
        $html = $this->render("Before\n\n" . self::CARD_URL . "\n\nAfter");

        $this->assertRegularCard($html);
        $this->assertStringContainsString('<p>Before</p>', $html);
        $this->assertStringContainsString('<p>After</p>', $html);

        $this->assertCount(1, $this->iframelyRequests());
        $query = self::queryOf($this->iframelyRequests()[0]);
        $this->assertSame(self::CARD_URL, $query['url']);
        $this->assertSame(self::API_KEY, $query['api_key']);
        $this->assertArrayNotHasKey('card', $query);
    }

    public function test_an_embed_block_with_a_page_url_becomes_a_card(): void
    {
        $block = '<!-- wp:embed {"url":"' . self::CARD_URL . '","type":"rich","providerNameSlug":"wikipedia"} -->' . "\n"
            . '<figure class="wp-block-embed is-type-rich is-provider-wikipedia wp-block-embed-wikipedia"><div class="wp-block-embed__wrapper">' . "\n"
            . self::CARD_URL . "\n"
            . '</div></figure>' . "\n"
            . '<!-- /wp:embed -->';

        $html = $this->render($block);

        $this->assertRegularCard($html);
        $this->assertStringContainsString('wp-block-embed-wikipedia', $html);
    }

    public function test_the_small_card_option_saved_in_the_url_reaches_the_api(): void
    {
        $html = $this->render(self::SMALL_CARD_URL);

        $this->assertSmallCard($html);
        $this->assertStringNotContainsString('padding-top: 120px', $html);

        $this->assertCount(1, $this->iframelyRequests());
        $query = self::queryOf($this->iframelyRequests()[0]);
        $this->assertSame('small', $query['card']);
        $this->assertSame(self::SMALL_CARD_URL, $query['url']);
        $this->assertSame(self::API_KEY, $query['api_key']);
    }

    public function test_the_small_card_option_works_in_the_shortcode(): void
    {
        $html = do_shortcode('[iframely]' . self::SMALL_CARD_URL . '[/iframely]');

        $this->assertSame(self::SMALL_CARD_HTML, $html);
        $this->assertSame('small', self::queryOf($this->iframelyRequests()[0])['card']);
    }

    public function test_small_and_regular_cards_of_one_page_are_cached_separately(): void
    {
        $content = self::CARD_URL . "\n\n" . self::SMALL_CARD_URL;

        $first = $this->render($content);
        $second = $this->render($content);

        $this->assertRegularCard($first);
        $this->assertSmallCard($first);
        $this->assertSame($first, $second);
        $this->assertCount(2, $this->iframelyRequests(), 'Each card is fetched once, then served from the cache.');
    }

    public function test_small_cards_can_be_the_default_for_the_site(): void
    {
        Options::setApiParams('iframe=card-small');
        $oembed = $this->freshOembed();

        $html = $oembed->get_html(self::CARD_URL);

        $this->assertSame(self::SMALL_CARD_HTML, $html);
        $query = self::queryOf($this->iframelyRequests()[0]);
        $this->assertSame('small', $query['card']);
        $this->assertSame('1', $query['iframe']);
    }

    public function test_the_option_of_one_url_overrides_the_default_of_the_site(): void
    {
        // Regular cards for the whole site.
        Options::setApiParams('iframe=card');
        $oembed = $this->freshOembed();

        $regular = $oembed->get_html(self::CARD_URL);
        $small = $oembed->get_html(self::SMALL_CARD_URL);

        $this->assertSame(self::CARD_HTML, $regular);
        $this->assertSame(self::SMALL_CARD_HTML, $small);
        $this->assertSame('1', self::queryOf($this->iframelyRequests()[0])['card']);
        $this->assertSame('small', self::queryOf($this->iframelyRequests()[1])['card']);
    }
}
