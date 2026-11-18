<?php

namespace Iframely\Embed;

class Feed
{
    public static function run(): void
    {
        # Disable on RSS feeds as there's no JavaScript to run embed codes.
        # Embeds are made while the_content runs, so that is where it has to happen:
        # the_content_feed comes after the first item has been rendered
        add_filter('the_content', [self::class, 'disableInFeeds'], 1);
        add_filter('the_content_feed', [self::class, 'iframely_disable_on_feed'], 1);
    }

    public static function disableInFeeds($content)
    {
        return is_feed() ? self::iframely_disable_on_feed($content) : $content;
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
