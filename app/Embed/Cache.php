<?php

namespace Iframely\Embed;

use Iframely\Options;

class Cache
{
    /**
     * How long to wait before trying again after a refresh that failed.
     */
    private const RETRY_REFRESH_AFTER = HOUR_IN_SECONDS;

    /**
     * How long to wait at most before asking again for a URL that could not be embedded.
     */
    private const RETRY_UNKNOWN_AFTER = DAY_IN_SECONDS;

    /**
     * The fetch WordPress is about to make for an embed that is not cached or has expired.
     *
     * @var array|null
     */
    private static $refresh;

    public static function run(): void
    {
        # Make WP cache work
        add_filter('embed_defaults', [self::class, 'iframely_embed_defaults'], 10, 1);

        add_filter('oembed_ttl', [self::class, 'updateCacheTtl'], 99, 4);
        add_filter('pre_oembed_result', [self::class, 'fetchStarted'], 1);
        add_filter('embed_maybe_make_link', [self::class, 'fetchFailed'], 10, 2);
    }

    public static function iframely_embed_defaults($args)
    {
        if (!Options::isCacheRefreshEnabled()) {
            return $args;
        }

        $query = Options::getApiParams();
        $key = Options::getApiKey();

        if (!empty($query) && Options::getCacheTtl()) {
            $args['api_params'] = $query;
        }
        if (!empty($key)) {
            $args['api_key'] = $key;
        }
        if (Amp::is_iframely_amp($args)) {
            $args['iframely'] = 'amp';
        }
        if (!Options::isPreviewsEnhanced()) {
            $args['self'] = '1';
        }

        return $args;
    }

    /**
     * WordPress serves a cached embed for as long as the post is not saved again,
     * whatever its age. To refresh embeds at regular intervals, an expired entry is
     * passed over once: WordPress fetches the embed again and replaces the entry if
     * that works. If it does not, fetchFailed() keeps the entry.
     */
    public static function updateCacheTtl($ttl, $url, $attr, $post_ID)
    {
        self::endRefresh();

        if (!Options::isCacheRefreshEnabled()) {
            return $ttl;
        }

        $cacheTtl = Options::getCacheTtl();
        if (!($cacheTtl > 0)) {
            return $ttl;
        }

        # Embeds outside of posts are not kept in post meta
        if (empty($post_ID)) {
            return $cacheTtl;
        }

        # Copy keys from wp-embed
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WordPress names its cache entries this way, see WP_Embed::shortcode()
        $suffix = md5($url . serialize($attr));
        $time = '_oembed_time_' . $suffix;
        $key = '_oembed_' . $suffix;
        $cache = (string)get_post_meta($post_ID, $key, true);
        $cacheTime = (int)get_post_meta($post_ID, $time, true);

        if ($cache !== '' && $cacheTime && (time() - $cacheTime <= $cacheTtl)) {
            return $ttl;
        }

        self::$refresh = [
            'url' => $url,
            'attr' => $attr,
            'post' => $post_ID,
            'key' => $key,
            'time' => $time,
            'ttl' => $cacheTtl,
            'html' => $cache === '{{unknown}}' ? '' : $cache,
            'usecache' => null,
        ];

        if ($cache !== '') {
            global $wp_embed;
            self::$refresh['usecache'] = $wp_embed->usecache;
            $wp_embed->usecache = false;
        }

        return $cacheTtl;
    }

    /**
     * WordPress has decided to fetch: the cache is used again for everything that follows.
     */
    public static function fetchStarted($result)
    {
        self::useCacheAgain();

        return $result;
    }

    /**
     * WordPress got no embed and is about to show a link.
     */
    public static function fetchFailed($output, $url)
    {
        $refresh = self::$refresh;
        self::endRefresh();

        if ($refresh === null || $refresh['url'] !== $url) {
            return $output;
        }

        if ($refresh['html'] !== '') {
            # The refresh failed: keep the embed we have, and try again later
            $retryAt = time() + min(self::RETRY_REFRESH_AFTER, $refresh['ttl']);
            update_post_meta($refresh['post'], $refresh['time'], $retryAt - $refresh['ttl']);

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The filter of WordPress, applied as WordPress does when it serves an embed from its cache.
            return apply_filters('embed_oembed_html', $refresh['html'], $url, $refresh['attr'], $refresh['post']);
        }

        if (get_post_meta($refresh['post'], $refresh['key'], true) === '{{unknown}}') {
            # WordPress keeps no time for a failure. Without one it would be retried on every view
            $retryAt = time() + min(self::RETRY_UNKNOWN_AFTER, $refresh['ttl']);
            update_post_meta($refresh['post'], $refresh['time'], $retryAt - $refresh['ttl']);
        }

        return $output;
    }

    private static function useCacheAgain(): void
    {
        if (self::$refresh !== null && self::$refresh['usecache'] !== null) {
            $GLOBALS['wp_embed']->usecache = self::$refresh['usecache'];
            self::$refresh['usecache'] = null;
        }
    }

    private static function endRefresh(): void
    {
        self::useCacheAgain();
        self::$refresh = null;
    }

    /**
     * The lifetimes that can be chosen, in seconds, longest first.
     *
     * @return int[]
     */
    public static function getTtlValues(): array
    {
        return [
            MONTH_IN_SECONDS,
            WEEK_IN_SECONDS * 3,
            WEEK_IN_SECONDS * 2,
            WEEK_IN_SECONDS,
            DAY_IN_SECONDS * 6,
            DAY_IN_SECONDS * 5,
            DAY_IN_SECONDS * 4,
            DAY_IN_SECONDS * 3,
            DAY_IN_SECONDS * 2,
            DAY_IN_SECONDS,
        ];
    }

    /**
     * The lifetimes with their names, for the settings form. The names are translated,
     * so this is for use once WordPress has loaded the translations (from init on).
     */
    public static function getTtlPresets(): array
    {
        return [
            MONTH_IN_SECONDS => __('1 month', 'iframely'),
            WEEK_IN_SECONDS * 3 => __('3 weeks', 'iframely'),
            WEEK_IN_SECONDS * 2 => __('2 weeks', 'iframely'),
            WEEK_IN_SECONDS => __('1 week', 'iframely'),
            DAY_IN_SECONDS * 6 => __('6 days', 'iframely'),
            DAY_IN_SECONDS * 5 => __('5 days', 'iframely'),
            DAY_IN_SECONDS * 4 => __('4 days', 'iframely'),
            DAY_IN_SECONDS * 3 => __('3 days', 'iframely'),
            DAY_IN_SECONDS * 2 => __('2 days', 'iframely'),
            DAY_IN_SECONDS => __('1 day', 'iframely'),
        ];
    }

    public static function getDefaultTtlValue(): int
    {
        return WEEK_IN_SECONDS;
    }
}
