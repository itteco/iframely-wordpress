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
    public const IFRAMELY_ENDPOINT_URL = IFRAMELY_API_ENDPOINT;

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
            # parse_str() decodes the values, and add_query_arg() does not encode what it adds
            $link = add_query_arg(array_map('rawurlencode', array_filter($params, 'is_string')), $link);
        }

        return $link;
    }
}
