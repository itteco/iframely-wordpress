<?php

namespace Iframely\Embed;

use Iframely\Options;
use Iframely\Utils;

class Amp
{
    public static function run(): void
    {
        # Make compatible with Automatic AMP-WP plugin: https://github.com/Automattic/amp-wp
        add_filter('oembed_fetch_url', [self::class, 'maybe_add_iframe_amp'], 10, 3);
        add_filter('embed_oembed_html', [self::class, 'iframely_filter_oembed_result'], 10, 3);
        add_filter('amp_content_embed_handlers', [self::class, 'maybe_disable_default_embed_handlers'], 10, 2);
    }

    public static function is_iframely_amp($args): bool
    {
        return
            (is_array($args) && array_key_exists('iframely', $args) && $args['iframely'] === 'amp')
            || (is_string($args) && Utils::stringContains($args, 'iframely=amp'))
            || (function_exists('is_amp_endpoint') && is_amp_endpoint());
    }

    public static function maybe_add_iframe_amp($provider, $args, $url)
    {
        if (self::is_iframely_amp($args) && Utils::stringContains($provider, 'iframe.ly')) {
            $provider = add_query_arg('amp', '1', $provider);
        }
        return $provider;
    }

    public static function iframely_filter_oembed_result($html, $url, $args)
    {
        if (Utils::stringContains($html, '<amp-iframe')) {  // covers "amp-iframely"
            // Avoid corrupted amp-iframe overflow div as a result of wpautop
            remove_filter('the_content', 'wpautop');
            // Restore wpautop if it was disabled
            add_filter('the_content', [self::class, 'iframely_autop_on_amp'], 1000);
        }

        return $html;
    }

    public static function iframely_autop_on_amp($content)
    {
        // Logic is taken from wpautop itself re <pre>
        if (Utils::stringContains($content, '<amp-iframe')) {
            $chunks = explode('</amp-iframe>', $content);
            $content = '';

            foreach ($chunks as $chunk) {
                $start = strpos($chunk, '<amp-iframe');
                // Malformed html?
                if ($start === false) {
                    $content .= $chunk;
                    continue;
                }

                $iframe = substr($chunk, $start) . '</amp-iframe>';
                $p = wpautop(substr($chunk, 0, $start));

                $content .= $p . $iframe;
            }
        } else {
            $content = wpautop($content);
        }

        return $content;
    }

    public static function maybe_disable_default_embed_handlers($embed_handler_classes)
    {
        //return ! get_site_option( 'iframely_only_shortcode' ) ? array() : $embed_handler_classes;
        if (!class_exists('Jetpack_AMP_Support') && Options::isBuiltinsReplaced()) {
            return [];
        }
        return $embed_handler_classes;
    }
}













