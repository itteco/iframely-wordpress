<?php

namespace Iframely\Embed;

use Iframely\Options;
use Iframely\Utils;

class Shortcode
{
    public static function run(): void
    {
        add_shortcode('iframely', [self::class, 'process']);
    }

    public static function process($attr, $url = '')
    {
        global $wp_embed;
        if (!empty($attr['url'])) {
            $url = str_replace(['&#038;', '&amp;'], ['&amp;', '&'], trim($attr['url']));
        }

        # The shortcode asks for Iframely by name. When the built-in providers of WordPress
        # are kept, they come first and would take the URLs they know
        if (Options::isBuiltinsReplaced()) {
            return $wp_embed->shortcode($attr, $url);
        }

        # An embed of the same URL made by a built-in provider is cached under the same key otherwise
        $attr = is_array($attr) ? $attr : [];
        $attr['iframely_shortcode'] = 1;

        $oembed = _wp_oembed_get_object();
        $providers = $oembed->providers;
        $oembed->providers = self::iframelyFirst($providers);
        try {
            return $wp_embed->shortcode($attr, $url);
        } finally {
            $oembed->providers = $providers;
        }
    }

    private static function iframelyFirst(array $providers): array
    {
        $iframely = array_filter($providers, static function ($provider) {
            return is_array($provider) && Utils::stringContains((string)$provider[0], 'iframe.ly');
        });

        return $iframely + $providers;
    }
}
