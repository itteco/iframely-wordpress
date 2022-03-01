<?php

namespace Iframely;

use Iframely\Embed\Amp;
use Iframely\Embed\Cache;
use Iframely\Embed\Feed;
use Iframely\Embed\Gutenberg;
use Iframely\Embed\Oembed;
use Iframely\Embed\Shortcode;

class Embed
{
    public const IFRAMELY_ENDPOINT_URL = 'https://iframe.ly/api/oembed';

    public static function run(): void
    {
        Oembed::run();
        Feed::run();
        Amp::run();
        Gutenberg::run();
        Cache::run();
        Shortcode::run();
    }

    public static function createApiLink(): string
    {
        $key = Options::getApiKey();
        $query = Options::getApiParams();
        $origin = preg_replace('#^https?://#i', '', get_bloginfo('url'));

        $link = add_query_arg([
            'origin' => $origin,
            'api_key' => $key,
        ], self::IFRAMELY_ENDPOINT_URL);

        if (!empty($query)) {
            parse_str($query, $params);
            $link = add_query_arg($params, $link);
        }

        return $link;
    }
}
