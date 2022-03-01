<?php

namespace Iframely\Embed;

class Feed
{
    public static function run(): void
    {
        # Disable on RSS feeds as there's no JavaScript to run embed codes
        add_filter('the_content_feed', [self::class, 'iframely_disable_on_feed'], 1, 99);
    }

    public static function iframely_disable_on_feed($content)
    {
        add_filter('embed_defaults', [self::class, 'iframely_add_feed_arg']);
        wp_oembed_remove_provider('#https?://[^\s]+#i');
        return $content;
    }

    public static function iframely_add_feed_arg($args)
    {
        $args['feed'] = 1;
        return $args;
    }
}
