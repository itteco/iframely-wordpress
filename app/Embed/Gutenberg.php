<?php

namespace Iframely\Embed;

use Iframely\Plugin;
use Iframely\Utils;
use Iframely\Options;

class Gutenberg
{
    public static function run(): void
    {
        add_action('plugins_loaded', [self::class, 'init']);
    }

    public static function init(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        # 'oembed_default_width' filter is used only in oembed/1.0/rest
        # i.e in the editor and self-oEmbed discovery
        add_filter('oembed_default_width', [self::class, 'iframely_flag_ajax_oembed']);
        add_filter('oembed_request_post_id', [self::class, 'maybe_remove_wp_self_embeds_in_guttenberg'], 10, 2);
        add_filter('embed_oembed_html', [self::class, 'filter_oembed_result'], 20, 3);

        # load assets
        add_action('enqueue_block_editor_assets', [self::class, 'iframely_gutenberg_loader']);
    }

    public static function iframely_flag_ajax_oembed($width)
    {
        add_filter('embed_defaults', [self::class, 'iframely_bust_gutenberg_cache'], 10, 1);
        add_filter('oembed_fetch_url', [self::class, 'maybe_add_gutenberg_1'], 20, 3);
        add_filter('oembed_result', [self::class, 'inject_events_proxy_to_gutenberg'], 10, 3);

        # The core's code doesn't even bother to look into default values and just hardcodes 600.
        # since we use the filter anyway - let's fix that
        if (!empty($GLOBALS['content_width'])) {
            $width = (int)$GLOBALS['content_width'];
        }
        return $width;
    }

    public static function iframely_bust_gutenberg_cache($args)
    {
        $args['gutenberg'] = 2;
        return $args;
    }

    public static function maybe_add_gutenberg_1($provider, $url, $args)
    {
        if (Utils::stringContains($provider, 'iframe.ly')) {
            $provider = add_query_arg('iframe', '1', $provider);
            $provider = add_query_arg('gutenberg', '2', $provider);
        }
        return $provider;
    }

    public static function inject_events_proxy_to_gutenberg($html, $url, $args)
    {
        $html = str_replace('"//cdn.iframe.ly', '"https://cdn.iframe.ly', $html);

        if (!empty(trim($html))) { // != trims $html
            return $html . '<script type="text/javascript">window.addEventListener("message",function(e){window.top.postMessage(e.data,"*");},false);</script>';
        }
        return $html;
    }

    public static function filter_oembed_result($cache) {
        $cache = str_replace(
           ['"//cdn.iframe.ly', 'window.parent.postMessage(e.data,"*")'],
           ['"https://cdn.iframe.ly', 'window.top.postMessage(e.data,"*")'],
         $cache);
        return $cache;
    }

    public static function maybe_remove_wp_self_embeds_in_guttenberg($post_id, $url)
    {
        return Options::isPreviewsEnhanced() ? false : $post_id;
    }

    public static function iframely_gutenberg_loader(): void
    {
        wp_register_script('iframely-gutenberg', Plugin::asset('index.js'), ['wp-block-editor', 'wp-components', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n'], IFRAMELY_VERSION, true);
        wp_enqueue_script('iframely-embed', 'https://if-cdn.com/embed.js', ['jquery'], IFRAMELY_VERSION);
        wp_enqueue_script('iframely-options', 'https://if-cdn.com/options.js', ['jquery'], IFRAMELY_VERSION);
        wp_enqueue_script('iframely-gutenberg');
    }
}