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

        # WordPress answers for links to posts of this site itself, before any provider is asked.
        # That disables our summary cards for own domain. While WordPress looks for its answer
        # (priority 10), it is told that the link is not a post. Answers of other plugins stay.
        add_filter('pre_oembed_result', [self::class, 'beforeOwnPostLookup'], 9);
        add_filter('pre_oembed_result', [self::class, 'afterOwnPostLookup'], 11);

        # Always add iframely as oembed provider for any iframe.ly short link
        wp_oembed_add_provider('#https?://iframe\.ly/.+#i', Embed::createApiLink(), true);

        # Fix URL query-string settings by replacing &iframe=card into &iframe=1&card=1
        add_filter('oembed_fetch_url', [self::class, 'maybe_replace_iframe_card'], 10, 3);
    }

    public static function beforeOwnPostLookup($result)
    {
        if (Options::isPreviewsEnhanced()) {
            add_filter('oembed_request_post_id', [self::class, 'noOwnPost'], PHP_INT_MAX);
        }

        return $result;
    }

    public static function afterOwnPostLookup($result)
    {
        remove_filter('oembed_request_post_id', [self::class, 'noOwnPost'], PHP_INT_MAX);

        return $result;
    }

    public static function noOwnPost()
    {
        return false;
    }

    public static function maybe_reverse_oembed_providers($providers)
    {
        # WordPress asks for the list once, when it sets up its providers. The [iframely] shortcode
        # puts Iframely first on its own, see Shortcode::process()
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
