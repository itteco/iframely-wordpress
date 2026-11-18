<?php

namespace Iframely\Tests;

use Iframely\Embed\Cache;
use Iframely\Options;

/**
 * "Evergreen cache": periodic refresh of the embed HTML that WordPress caches in
 * post meta, and the arguments that make up the cache key.
 */
class CacheTest extends TestCase
{
    private const URL = 'https://example.com/some/article';
    private const ATTR = ['width' => 500, 'height' => 750];

    /**
     * @var int
     */
    private $postId;

    public function set_up(): void
    {
        parent::set_up();

        $this->postId = self::factory()->post->create();
        Options::setCacheRefresh(true);
        Options::setCacheTtl(WEEK_IN_SECONDS);
    }

    public function tear_down(): void
    {
        wp_reset_postdata();
        $GLOBALS['wp_embed']->post_ID = null;
        $GLOBALS['wp_embed']->usecache = true;
        $GLOBALS['wp_embed']->return_false_on_fail = false;

        parent::tear_down();
    }

    private static function cacheKey(): string
    {
        return '_oembed_' . md5(self::URL . serialize(self::ATTR));
    }

    private static function timeKey(): string
    {
        return '_oembed_time_' . md5(self::URL . serialize(self::ATTR));
    }

    private function storeCachedEmbed(int $age): void
    {
        update_post_meta($this->postId, self::cacheKey(), self::EMBED_HTML);
        update_post_meta($this->postId, self::timeKey(), time() - $age);
    }

    // Cache key arguments.

    public function test_embed_arguments_are_untouched_when_cache_refresh_is_off(): void
    {
        Options::setCacheRefresh(false);

        $this->assertSame(self::ATTR, Cache::iframely_embed_defaults(self::ATTR));
    }

    public function test_api_key_is_part_of_the_cache_key(): void
    {
        Options::setPreviewsEnhance(true);

        $this->assertSame(self::ATTR + ['api_key' => self::API_KEY], Cache::iframely_embed_defaults(self::ATTR));
    }

    public function test_extra_query_string_is_part_of_the_cache_key(): void
    {
        Options::setPreviewsEnhance(true);
        Options::setApiParams('theme=dark');

        $this->assertSame(
            self::ATTR + ['api_params' => 'theme=dark', 'api_key' => self::API_KEY],
            Cache::iframely_embed_defaults(self::ATTR)
        );
    }

    public function test_extra_query_string_is_left_out_of_the_cache_key_without_a_lifetime(): void
    {
        Options::setPreviewsEnhance(true);
        Options::setApiParams('theme=dark');
        Options::setCacheTtl(0);

        $this->assertArrayNotHasKey('api_params', Cache::iframely_embed_defaults(self::ATTR));
    }

    public function test_cache_key_differs_when_own_posts_are_not_sent_to_iframely(): void
    {
        Options::setPreviewsEnhance(false);

        $this->assertSame('1', Cache::iframely_embed_defaults(self::ATTR)['self']);
    }

    public function test_amp_marker_is_kept_in_the_cache_key(): void
    {
        $args = Cache::iframely_embed_defaults(self::ATTR + ['iframely' => 'amp']);

        $this->assertSame('amp', $args['iframely']);
    }

    // Lifetime.

    public function test_lifetime_is_untouched_when_cache_refresh_is_off(): void
    {
        Options::setCacheRefresh(false);
        $this->storeCachedEmbed(YEAR_IN_SECONDS);

        $this->assertSame(DAY_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId));
        $this->assertSame(self::EMBED_HTML, get_post_meta($this->postId, self::cacheKey(), true));
    }

    public function test_lifetime_is_untouched_when_no_lifetime_is_configured(): void
    {
        Options::setCacheTtl(0);
        $this->storeCachedEmbed(YEAR_IN_SECONDS);

        $this->assertSame(DAY_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId));
        $this->assertSame(self::EMBED_HTML, get_post_meta($this->postId, self::cacheKey(), true));
    }

    public function test_a_fresh_cache_entry_is_kept(): void
    {
        $this->storeCachedEmbed(WEEK_IN_SECONDS - HOUR_IN_SECONDS);

        $this->assertSame(DAY_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId));
        $this->assertSame(self::EMBED_HTML, get_post_meta($this->postId, self::cacheKey(), true));
        $this->assertNotSame('', get_post_meta($this->postId, self::timeKey(), true));
    }

    public function test_a_stale_cache_entry_is_kept_and_passed_over_for_one_fetch(): void
    {
        $this->storeCachedEmbed(WEEK_IN_SECONDS + HOUR_IN_SECONDS);

        $this->assertSame(WEEK_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId));
        $this->assertSame(self::EMBED_HTML, get_post_meta($this->postId, self::cacheKey(), true));
        $this->assertFalse($GLOBALS['wp_embed']->usecache, 'WordPress should look past the stale entry.');

        // The next embed is served from the cache again.
        Cache::updateCacheTtl(DAY_IN_SECONDS, 'https://example.com/another', self::ATTR, $this->postId);
        $this->assertTrue($GLOBALS['wp_embed']->usecache);
    }

    public function test_an_embed_that_was_never_cached_gets_the_configured_lifetime(): void
    {
        $this->assertSame(WEEK_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId));
    }

    public function test_entries_of_other_embeds_in_the_same_post_are_kept(): void
    {
        $this->storeCachedEmbed(YEAR_IN_SECONDS);
        update_post_meta($this->postId, '_oembed_0123456789abcdef0123456789abcdef', 'other embed');

        Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, $this->postId);

        $this->assertSame('other embed', get_post_meta($this->postId, '_oembed_0123456789abcdef0123456789abcdef', true));
    }

    public function test_embeds_outside_of_a_post_get_the_configured_lifetime(): void
    {
        $this->assertSame(WEEK_IN_SECONDS, Cache::updateCacheTtl(DAY_IN_SECONDS, self::URL, self::ATTR, null));
    }

    // Through WordPress.

    public function test_a_rendered_embed_is_fetched_once_and_then_served_from_the_cache(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithEmbed();

        $first = $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');
        $second = $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');

        $this->assertSame(self::EMBED_HTML, $first);
        $this->assertSame(self::EMBED_HTML, $second);
        $this->assertCount(1, $this->iframelyRequests());
    }

    public function test_a_rendered_embed_is_fetched_again_once_its_cache_entry_is_stale(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');

        $this->ageCachedEmbeds(WEEK_IN_SECONDS + HOUR_IN_SECONDS);
        $this->respondWithEmbed('<div class="iframely-test-embed">refreshed</div>');
        $html = $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');

        $this->assertSame('<div class="iframely-test-embed">refreshed</div>', $html);
        $this->assertCount(2, $this->iframelyRequests());
    }

    public function test_a_stale_embed_is_not_fetched_again_when_cache_refresh_is_off(): void
    {
        Options::setCacheRefresh(false);
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');

        $this->ageCachedEmbeds(YEAR_IN_SECONDS);
        $html = $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->iframelyRequests());
    }

    // When the API fails.

    private function view(): string
    {
        return $GLOBALS['wp_embed']->run_shortcode('[embed]' . self::URL . '[/embed]');
    }

    private function link(): string
    {
        return '<a href="' . self::URL . '">' . self::URL . '</a>';
    }

    public function test_an_expired_embed_stays_when_it_cannot_be_refreshed(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $this->view();
        $this->ageCachedEmbeds(WEEK_IN_SECONDS + HOUR_IN_SECONDS);

        $this->respondWithError();
        $views = [$this->view(), $this->view(), $this->view()];

        $this->assertSame([self::EMBED_HTML, self::EMBED_HTML, self::EMBED_HTML], $views);
        $this->assertCount(2, $this->iframelyRequests(), 'One failed attempt, then the old embed without asking again.');
        $this->assertTrue($GLOBALS['wp_embed']->usecache);
    }

    public function test_a_failed_refresh_is_tried_again_an_hour_later(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $this->view();
        $this->ageCachedEmbeds(WEEK_IN_SECONDS + HOUR_IN_SECONDS);
        $this->respondWithError();
        $this->view();

        // 59 minutes later nothing is asked, two minutes after that it is.
        $this->shiftCachedEmbeds(59 * MINUTE_IN_SECONDS);
        $this->view();
        $this->assertCount(2, $this->iframelyRequests());

        $this->shiftCachedEmbeds(2 * MINUTE_IN_SECONDS);
        $this->respondWithEmbed('<div class="iframely-test-embed">refreshed</div>');
        $html = $this->view();

        $this->assertSame('<div class="iframely-test-embed">refreshed</div>', $html);
        $this->assertCount(3, $this->iframelyRequests());
        $this->assertSame('<div class="iframely-test-embed">refreshed</div>', $this->view());
        $this->assertCount(3, $this->iframelyRequests());
    }

    public function test_an_expired_embed_survives_a_failed_refresh_that_reports_no_link(): void
    {
        // The classic editor asks for embeds this way.
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $this->view();
        $this->ageCachedEmbeds(WEEK_IN_SECONDS + HOUR_IN_SECONDS);

        $this->respondWithError();
        $GLOBALS['wp_embed']->return_false_on_fail = true;
        $this->assertSame('', $this->view());
        $GLOBALS['wp_embed']->return_false_on_fail = false;

        $this->assertSame([self::EMBED_HTML], $this->cachedEmbeds());
        $this->respondWithError();
        $this->assertSame(self::EMBED_HTML, $this->view());
    }

    public function test_a_url_that_cannot_be_embedded_is_asked_for_once(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithError();

        $views = [$this->view(), $this->view(), $this->view(), $this->view(), $this->view()];

        $this->assertSame(array_fill(0, 5, $this->link()), $views);
        $this->assertCount(1, $this->iframelyRequests());
    }

    public function test_a_url_that_could_not_be_embedded_is_tried_again_a_day_later(): void
    {
        $this->enterPost($this->postId);
        $this->respondWithError();
        $this->view();

        $this->shiftCachedEmbeds(DAY_IN_SECONDS - MINUTE_IN_SECONDS);
        $this->view();
        $this->assertCount(1, $this->iframelyRequests());

        $this->shiftCachedEmbeds(2 * MINUTE_IN_SECONDS);
        $this->respondWithEmbed();
        $this->assertSame(self::EMBED_HTML, $this->view());
        $this->assertCount(2, $this->iframelyRequests());
    }

    public function test_a_failure_recorded_without_a_time_is_asked_for_once_more(): void
    {
        // What WordPress itself stores, and what earlier versions of the plugin left behind.
        $this->enterPost($this->postId);
        $this->respondWithError();
        $this->view();
        foreach (array_keys(get_post_meta($this->postId)) as $key) {
            if (strpos($key, '_oembed_time_') === 0) {
                delete_post_meta($this->postId, $key);
            }
        }

        $this->view();
        $this->view();
        $this->view();

        $this->assertCount(2, $this->iframelyRequests());
    }

    public function test_only_the_expired_embed_of_a_post_is_fetched_again(): void
    {
        $other = 'https://example.com/another/article';
        $this->enterPost($this->postId);
        $this->respondWithEmbed();
        $content = self::URL . "\n\n" . $other;
        $GLOBALS['wp_embed']->autoembed($content);
        $this->assertCount(2, $this->iframelyRequests());

        // Only the first one expires.
        $first = '_oembed_time_' . md5(self::URL . serialize(wp_embed_defaults(self::URL)));
        $this->assertNotSame('', get_post_meta($this->postId, $first, true));
        update_post_meta($this->postId, $first, time() - WEEK_IN_SECONDS - HOUR_IN_SECONDS);
        $GLOBALS['wp_embed']->autoembed($content);

        $requests = $this->iframelyRequests();
        $this->assertCount(3, $requests);
        $this->assertSame(self::URL, self::queryOf($requests[2])['url']);
        $this->assertTrue($GLOBALS['wp_embed']->usecache);
    }

    /**
     * @return string[] What WordPress keeps for the embeds of the post.
     */
    private function cachedEmbeds(): array
    {
        $cached = [];
        foreach (get_post_meta($this->postId) as $key => $values) {
            if (strpos($key, '_oembed_') === 0 && strpos($key, '_oembed_time_') !== 0) {
                $cached[] = $values[0];
            }
        }

        return $cached;
    }

    /**
     * Moves the cached entries of the post into the past, as if time had gone by.
     */
    private function shiftCachedEmbeds(int $seconds): void
    {
        foreach (get_post_meta($this->postId) as $key => $values) {
            if (strpos($key, '_oembed_time_') === 0) {
                update_post_meta($this->postId, $key, (int)$values[0] - $seconds);
            }
        }
    }

    private function ageCachedEmbeds(int $age): void
    {
        $aged = 0;
        foreach (array_keys(get_post_meta($this->postId)) as $key) {
            if (strpos($key, '_oembed_time_') === 0) {
                update_post_meta($this->postId, $key, time() - $age);
                $aged++;
            }
        }
        $this->assertSame(1, $aged, 'Expected exactly one cached embed in the post.');
    }

    // Presets.

    public function test_the_lifetimes_and_their_names_are_the_same_list(): void
    {
        $this->assertSame(Cache::getTtlValues(), array_keys(Cache::getTtlPresets()));
    }

    public function test_default_lifetime_is_one_of_the_presets(): void
    {
        $presets = Cache::getTtlPresets();

        $this->assertSame(WEEK_IN_SECONDS, Cache::getDefaultTtlValue());
        $this->assertArrayHasKey(Cache::getDefaultTtlValue(), $presets);
        $this->assertSame(DAY_IN_SECONDS, min(array_keys($presets)));
        $this->assertSame(MONTH_IN_SECONDS, max(array_keys($presets)));
        $this->assertCount(10, $presets);
    }
}
