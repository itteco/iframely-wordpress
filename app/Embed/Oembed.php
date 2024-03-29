<?php

namespace Iframely\Embed;

use Iframely\Embed;
use Iframely\Options;
use Iframely\Utils;

class Oembed
{
    public static function run(): void
    {
        # Always add Iframely as provider. Last to the list. If not 'only_shortcode', then this provider will disable all default ones

        # Add iframely as oembed provider for ANY url, yes it will process any url on separate line with wp oembed functions
        wp_oembed_add_provider('#https?://[^\s]+#i', Embed::createApiLink(), true);

        # Make the Iframely endpoint to be the first in queue, otherwise default regexp are precedent
        add_filter('oembed_providers', [self::class, 'maybe_reverse_oembed_providers']);

        # Remove short-circuit for self-embeds, that forces it for all the sites and disables our summary cards for own domain
        add_filter('pre_oembed_result', [self::class, 'maybe_remove_wp_self_embeds'], PHP_INT_MAX, 3);
        # alternatively: remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );

        # Always add iframely as oembed provider for any iframe.ly short link
        wp_oembed_add_provider('#https?://iframe\.ly/.+#i', Embed::createApiLink(), true);

        # Fix URL query-string settings by replacing &iframe=card into &iframe=1&card=1
        add_filter('oembed_fetch_url', [self::class, 'maybe_replace_iframe_card'], 10, 3);
    }

    public static function maybe_remove_wp_self_embeds($result, $url, $args)
    {
        return Options::isPreviewsEnhanced() ? null : $result;
        //return get_site_option('publish_iframely_cards') ? null : $result;
    }

    public static function maybe_reverse_oembed_providers($providers)
    {
        // iframely_only_shortcode option is unset in shortcode, so that the filter can work. Then returned back.
        if (Options::isBuiltinsReplaced()) {
            return array_reverse($providers);
        }

        return $providers;
    }

    public static function maybe_replace_iframe_card($provider, $url, $args)
    {
        if (Utils::stringContains($provider, 'iframe.ly') && Utils::stringContains($provider, 'iframe=card')) {
            if (Utils::stringContains($provider, 'iframe=card-small')) {
                $provider = add_query_arg('card', 'small', $provider);
            } else {
                $provider = add_query_arg('card', '1', $provider);
            }
            $provider = add_query_arg('iframe', '1', $provider);
        }
        return $provider;
    }
}
