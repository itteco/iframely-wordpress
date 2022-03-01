<?php

namespace Iframely\Embed;

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
        return $wp_embed->shortcode($attr, $url);
    }
}
