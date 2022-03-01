<?php

namespace Iframely\Embed;

use Iframely\Options;

class Cache
{
    public static function run(): void
    {
        # Make WP cache work
        add_filter('embed_defaults', [self::class, 'iframely_embed_defaults'], 10, 1);

        add_filter('oembed_ttl', [self::class, 'updateCacheTtl'], 99, 4);
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

    public static function updateCacheTtl($ttl, $url, $attr, $post_ID)
    {
        if (!Options::isCacheRefreshEnabled()) {
            return $ttl;
        }

        $cacheTtl = Options::getCacheTtl();
        if (!($cacheTtl > 0)) {
            return $ttl;
        }

        global $wp_embed;

        # Copy keys from wp-embed
        $suffix = md5($url . serialize($attr));
        $time = '_oembed_time_' . $suffix;
        $key = '_oembed_' . $suffix;
        $cacheTime = get_post_meta($post_ID, $time, true);

        if (!$cacheTime || (time() - $cacheTime > $cacheTtl)) {
            $wp_embed->usecache = false;
            delete_post_meta($post_ID, $time);
            delete_post_meta($post_ID, $key);
            return $cacheTtl;
        }

        return $ttl;
    }

    public static function getTtlPresets(): array
    {
        return [
            MONTH_IN_SECONDS * 30 => __('1 month', 'iframely'),
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
