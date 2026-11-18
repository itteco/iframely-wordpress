<?php

namespace Iframely\Tests;

use Iframely\Options;

class ShortcodeTest extends TestCase
{
    private const VIDEO_URL = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';

    /**
     * @var array|null
     */
    private $providers;

    public function tear_down(): void
    {
        if ($this->providers !== null) {
            _wp_oembed_get_object()->providers = $this->providers;
            $this->providers = null;
        }
        wp_reset_postdata();
        $GLOBALS['wp_embed']->post_ID = null;

        parent::tear_down();
    }

    /**
     * Puts the site into the state of "Include built-in providers" being off:
     * WordPress asks its own providers first.
     */
    private function keepBuiltinProviders(): void
    {
        $this->setBuiltinsReplaced(false);
    }

    private function setBuiltinsReplaced(bool $replaced): void
    {
        Options::setBuiltinsReplace($replaced);
        if ($this->providers === null) {
            $this->providers = _wp_oembed_get_object()->providers;
        }
        _wp_oembed_get_object()->providers = $this->freshOembed()->providers;
    }

    /**
     * @return string[] Hosts of the requests made, in order.
     */
    private function requestedHosts(): array
    {
        return array_map(static function ($url) {
            return (string)wp_parse_url($url, PHP_URL_HOST);
        }, $this->requestedUrls());
    }

    public function test_shortcode_is_registered(): void
    {
        $this->assertTrue(shortcode_exists('iframely'));
    }

    public function test_embeds_the_enclosed_url_through_iframely(): void
    {
        $this->respondWithEmbed();

        $html = do_shortcode('[iframely]https://example.com/some/article[/iframely]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->iframelyRequests());
        $this->assertSame('https://example.com/some/article', self::queryOf($this->iframelyRequests()[0])['url']);
    }

    /**
     * @dataProvider urlAttributeProvider
     */
    public function test_embeds_the_url_attribute_with_ampersands_decoded(string $attribute): void
    {
        $this->respondWithEmbed();

        $html = do_shortcode('[iframely url="' . $attribute . '"]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertSame('https://example.com/watch?a=1&b=2', self::queryOf($this->iframelyRequests()[0])['url']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function urlAttributeProvider(): array
    {
        return [
            'plain ampersand' => ['https://example.com/watch?a=1&b=2'],
            'named entity' => ['https://example.com/watch?a=1&amp;b=2'],
            'numeric entity' => ['https://example.com/watch?a=1&#038;b=2'],
            'surrounding whitespace' => [' https://example.com/watch?a=1&b=2 '],
        ];
    }

    public function test_falls_back_to_a_link_when_iframely_has_no_embed(): void
    {
        $this->respondWithJson(['error' => 'Not found', 'status' => 404], 404);

        $html = do_shortcode('[iframely]https://example.com/missing[/iframely]');

        $this->assertSame('<a href="https://example.com/missing">https://example.com/missing</a>', $html);
    }

    public function test_the_shortcode_goes_to_iframely_for_a_url_that_wordpress_has_a_provider_for(): void
    {
        $this->keepBuiltinProviders();
        $this->respondWithEmbed();
        $providers = _wp_oembed_get_object()->providers;

        // A URL on its own goes to the provider of WordPress, the shortcode does not.
        wp_oembed_get(self::VIDEO_URL);
        $html = do_shortcode('[iframely]' . self::VIDEO_URL . '[/iframely]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertSame(['www.youtube.com', 'iframe.ly'], $this->requestedHosts());
        $this->assertSame(self::VIDEO_URL, self::queryOf($this->iframelyRequests()[0])['url']);
        // WordPress has its providers back in their order.
        $this->assertSame(array_keys($providers), array_keys(_wp_oembed_get_object()->providers));
        wp_oembed_get(self::VIDEO_URL);
        $this->assertSame(['www.youtube.com', 'iframe.ly', 'www.youtube.com'], $this->requestedHosts());
    }

    public function test_the_shortcode_does_not_reuse_an_embed_that_a_provider_of_wordpress_made(): void
    {
        $this->keepBuiltinProviders();
        $this->respondWithEmbed();
        $this->enterPost(self::factory()->post->create());

        // Both are cached in the post. They must not share one entry.
        $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::VIDEO_URL . '[/embed]');
        do_shortcode('[iframely]' . self::VIDEO_URL . '[/iframely]');
        do_shortcode('[iframely]' . self::VIDEO_URL . '[/iframely]');
        $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::VIDEO_URL . '[/embed]');

        $this->assertSame(['www.youtube.com', 'iframe.ly'], $this->requestedHosts());
    }

    public function test_the_shortcode_shares_the_cache_with_plain_urls_when_iframely_replaces_the_builtin_providers(): void
    {
        // Nothing changes for these sites, their cached embeds stay in use.
        $this->setBuiltinsReplaced(true);
        $this->respondWithEmbed();
        $this->enterPost(self::factory()->post->create());

        $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::VIDEO_URL . '[/embed]');
        $html = do_shortcode('[iframely]' . self::VIDEO_URL . '[/iframely]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->requestedUrls());
    }
}
