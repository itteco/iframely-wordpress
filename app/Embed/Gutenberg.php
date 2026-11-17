<?php

namespace Iframely\Embed;

use Iframely\Plugin;
use Iframely\Utils;
use Iframely\Options;

class Gutenberg
{
    /**
     * Parameters of the API request that say who asks, for which page and in which
     * format. The options of an embed cannot set them.
     */
    private const RESERVED_URL_OPTIONS = ['api_key', 'key', 'origin', 'url', 'uri', 'format', 'callback'];

    public static function run(): void
    {
        add_action('plugins_loaded', [self::class, 'init']);
    }

    public static function init(): void
    {
        # Always extract &iframely={serialized options} that we append to URLs
        # and add these options into the oEmbed endpoint's query-string parameters
        add_filter('oembed_fetch_url', [self::class, 'maybe_add_iframely_url_options'], 20, 3);

        # The editor asks for its previews on one REST route, open to users who can edit posts.
        # Who the user is does not matter here, and is not known yet for sure
        add_filter('rest_request_before_callbacks', [self::class, 'startEditorPreview'], 10, 3);
        add_filter('rest_request_after_callbacks', [self::class, 'endEditorPreview'], 10, 3);

        # load assets
        add_action('enqueue_block_editor_assets', [self::class, 'iframely_gutenberg_loader']);
    }

    /**
     * Embeds made for a preview in the editor differ from the ones on the site: they
     * are asked for as iframes, cached apart, and carry a script that passes their
     * messages on to the editor. None of that may reach other requests.
     */
    public static function startEditorPreview($response, $handler, $request)
    {
        if (!self::isEditorPreview($request)) {
            return $response;
        }

        add_filter('embed_defaults', [self::class, 'iframely_bust_gutenberg_cache'], 10, 1);
        add_filter('oembed_fetch_url', [self::class, 'maybe_add_gutenberg_1'], 20, 3);
        add_filter('oembed_result', [self::class, 'inject_events_proxy_to_gutenberg'], 10, 3);
        add_filter('oembed_request_post_id', [self::class, 'maybe_remove_wp_self_embeds_in_guttenberg'], 10, 2);

        # The core's code doesn't even bother to look into default values and just hardcodes 600.
        if (!empty($GLOBALS['content_width']) && !isset($request->get_query_params()['maxwidth'])) {
            $request->set_param('maxwidth', (int)$GLOBALS['content_width']);
        }

        return $response;
    }

    public static function endEditorPreview($response, $handler, $request)
    {
        if (self::isEditorPreview($request)) {
            remove_filter('embed_defaults', [self::class, 'iframely_bust_gutenberg_cache'], 10);
            remove_filter('oembed_fetch_url', [self::class, 'maybe_add_gutenberg_1'], 20);
            remove_filter('oembed_result', [self::class, 'inject_events_proxy_to_gutenberg'], 10);
            remove_filter('oembed_request_post_id', [self::class, 'maybe_remove_wp_self_embeds_in_guttenberg'], 10);
        }

        return $response;
    }

    private static function isEditorPreview($request): bool
    {
        return $request instanceof \WP_REST_Request && $request->get_route() === '/oembed/1.0/proxy';
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
            $provider = add_query_arg('import', '0', $provider);
            $provider = add_query_arg('ssl', '1', $provider);
            $provider = add_query_arg('gutenberg', '2', $provider);
        }
        return $provider;
    }

    public static function maybe_add_iframely_url_options($provider, $url, $args)
    {
        if (!Utils::stringContains($provider, 'iframe.ly')) {
            return $provider;
        }

        foreach (self::getUrlOptions($url) as $key => $value) {
            # add_query_arg() does not encode what it adds
            $provider = add_query_arg($key, rawurlencode($value), $provider);
        }

        return $provider;
    }

    /**
     * The options an author has chosen for one embed, as they go into the API request.
     * Anyone who can write a post can write them, so they are checked.
     *
     * @return array<string, string>
     */
    public static function getUrlOptions($url): array
    {
        # Options are added to the URL in utils.js this way:
        # 'iframely=' + encodeURIComponent(window.btoa(JSON.stringify(query)));
        if (!is_string($url) || !Utils::stringContains($url, 'iframely=')) {
            return [];
        }

        $params = [];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
        if (!isset($params['iframely']) || !is_string($params['iframely'])) {
            return [];
        }

        # parse_str() has decoded the value. It also turns a "+" that was not encoded into a space
        $decoded = json_decode((string)base64_decode(str_replace(' ', '+', $params['iframely'])));

        # Some WPs have our legacy values &iframely=less in their posts
        if (!is_object($decoded)) {
            return [];
        }

        $options = [];
        foreach (get_object_vars($decoded) as $key => $value) {
            $key = (string)$key;
            if (!preg_match('/^[\w.-]+$/', $key) || in_array(strtolower($key), self::RESERVED_URL_OPTIONS, true)) {
                continue;
            }

            if (is_bool($value)) {
                # The API reads "false" as off. It takes "0" for on, and so does it a missing option that is on by default
                $options[$key] = $value ? 'true' : 'false';
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $options[$key] = (string)$value;
            }
        }

        return $options;
    }

    public static function inject_events_proxy_to_gutenberg($html, $url, $args)
    {
        if (!empty(trim($html))) {
            return $html .
                '<style>body{overflow: hidden}</style>' .
                '<script type="text/javascript">window.addEventListener("message",function(e){window.top.postMessage(e.data,"*");},false);</script>';
        }
        return $html;
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
