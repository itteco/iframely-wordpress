<?php

namespace Iframely\Tests;

use Iframely\Embed;
use Iframely\Embed\Oembed;
use Iframely\Options;

/**
 * Registration and ordering of Iframely as an oEmbed provider, and handling of
 * links to the site's own posts.
 */
class OembedTest extends TestCase
{
    private const YOUTUBE_URL = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';
    private const UNKNOWN_URL = 'https://example.com/some/article';

    public function test_registers_iframely_for_any_url_and_for_its_short_links(): void
    {
        $providers = $this->freshOembed()->providers;

        $this->assertSame([Embed::createApiLink(), true], $providers[self::CATCH_ALL_PROVIDER]);
        $this->assertSame([Embed::createApiLink(), true], $providers[self::SHORT_LINK_PROVIDER]);
    }

    public function test_iframely_handles_urls_that_wordpress_has_no_provider_for(): void
    {
        Options::setBuiltinsReplace(false);

        $provider = $this->freshOembed()->get_provider(self::UNKNOWN_URL, ['discover' => false]);

        $this->assertStringStartsWith(self::ENDPOINT, $provider);
    }

    public function test_wordpress_providers_keep_priority_by_default(): void
    {
        Options::setBuiltinsReplace(false);

        $provider = $this->freshOembed()->get_provider(self::YOUTUBE_URL, ['discover' => false]);

        $this->assertStringContainsString('youtube.com/oembed', $provider);
    }

    public function test_iframely_takes_priority_when_it_replaces_the_builtin_providers(): void
    {
        Options::setBuiltinsReplace(true);

        $provider = $this->freshOembed()->get_provider(self::YOUTUBE_URL, ['discover' => false]);

        $this->assertStringStartsWith(self::ENDPOINT, $provider);
    }

    public function test_iframely_short_links_always_go_to_iframely(): void
    {
        foreach ([true, false] as $replace) {
            Options::setBuiltinsReplace($replace);

            $provider = $this->freshOembed()->get_provider('https://iframe.ly/abc123', ['discover' => false]);

            $this->assertStringStartsWith(self::ENDPOINT, $provider);
        }
    }

    public function test_provider_list_is_reversed_only_when_builtins_are_replaced(): void
    {
        $providers = ['first' => ['a', false], 'second' => ['b', false]];

        Options::setBuiltinsReplace(true);
        $this->assertSame(['second', 'first'], array_keys(Oembed::maybe_reverse_oembed_providers($providers)));

        Options::setBuiltinsReplace(false);
        $this->assertSame(['first', 'second'], array_keys(Oembed::maybe_reverse_oembed_providers($providers)));
    }

    /**
     * "iframe=card" in the extra query string is shorthand that the API does not
     * understand; it is rewritten before the request is made.
     *
     * @dataProvider iframeCardProvider
     *
     * @param array<string, string> $expected
     */
    public function test_rewrites_the_iframe_card_shorthand(string $provider, array $expected): void
    {
        $result = Oembed::maybe_replace_iframe_card($provider, self::UNKNOWN_URL, []);

        $query = self::queryOf($result);
        foreach ($expected as $name => $value) {
            $this->assertSame($value, $query[$name] ?? null, $name);
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string|null>}>
     */
    public function iframeCardProvider(): array
    {
        return [
            'card' => [
                self::ENDPOINT . '?api_key=k&iframe=card',
                ['iframe' => '1', 'card' => '1', 'api_key' => 'k'],
            ],
            'small card' => [
                self::ENDPOINT . '?api_key=k&iframe=card-small',
                ['iframe' => '1', 'card' => 'small', 'api_key' => 'k'],
            ],
            'plain iframe is left alone' => [
                self::ENDPOINT . '?api_key=k&iframe=1',
                ['iframe' => '1', 'card' => null],
            ],
            'other providers are left alone' => [
                'https://www.youtube.com/oembed?iframe=card',
                ['iframe' => 'card', 'card' => null],
            ],
        ];
    }

    public function test_the_card_shorthand_from_the_settings_reaches_the_api_rewritten(): void
    {
        Options::setApiParams('iframe=card');
        $oembed = $this->freshOembed();
        $this->respondWithEmbed();

        $oembed->get_html(self::UNKNOWN_URL);

        $this->assertCount(1, $this->iframelyRequests());
        $query = self::queryOf($this->iframelyRequests()[0]);
        $this->assertSame('1', $query['iframe']);
        $this->assertSame('1', $query['card']);
        $this->assertSame(self::UNKNOWN_URL, $query['url']);
        $this->assertSame(self::API_KEY, $query['api_key']);
    }

    public function test_embeds_the_html_returned_by_iframely(): void
    {
        $this->respondWithEmbed();

        $this->assertSame(self::EMBED_HTML, wp_oembed_get(self::UNKNOWN_URL));
    }

    public function test_links_to_own_posts_use_the_wordpress_embed_by_default(): void
    {
        Options::setPreviewsEnhance(false);
        $permalink = get_permalink(self::factory()->post->create(['post_title' => 'Own post']));

        $html = wp_oembed_get($permalink);

        $this->assertStringContainsString('wp-embedded-content', $html);
        $this->assertSame([], $this->requestedUrls());
    }

    public function test_links_to_own_posts_go_to_iframely_when_previews_are_enhanced(): void
    {
        Options::setPreviewsEnhance(true);
        $permalink = get_permalink(self::factory()->post->create(['post_title' => 'Own post']));
        $this->respondWithEmbed();

        $html = wp_oembed_get($permalink);

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->iframelyRequests());
        $this->assertSame($permalink, self::queryOf($this->iframelyRequests()[0])['url']);
    }
}
