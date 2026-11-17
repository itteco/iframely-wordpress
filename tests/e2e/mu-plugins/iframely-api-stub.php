<?php
/**
 * Plugin Name: Iframely API stub (end-to-end tests)
 * Description: Answers requests to the Iframely API locally, so the end-to-end tests never call the real service. Mounted only into the wp-env tests site (.wp-env.tests.json).
 */

// PHPUnit runs in the same container and brings its own HTTP mocking.
if (function_exists('tests_add_filter')) {
    return;
}

/**
 * API keys starting with this prefix are accepted, everything else is rejected.
 */
const IFRAMELY_E2E_VALID_KEY_PREFIX = 'e2evalid';

/**
 * Stand-in for https://iframe.ly/api/oembed.
 */
add_filter('pre_http_request', static function ($preempt, $args, $url) {
    if (strpos($url, 'https://iframe.ly/api/oembed') !== 0) {
        return $preempt;
    }

    $query = [];
    wp_parse_str((string)wp_parse_url($url, PHP_URL_QUERY), $query);

    $log = get_option('iframely_e2e_requests', []);
    $log[] = $query;
    update_option('iframely_e2e_requests', $log, false);

    $respond = static function (array $data, int $code) {
        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => wp_json_encode($data),
            'response' => ['code' => $code, 'message' => get_status_header_desc($code)],
            'cookies' => [],
            'filename' => null,
        ];
    };

    if (strpos((string)($query['api_key'] ?? ''), IFRAMELY_E2E_VALID_KEY_PREFIX) !== 0) {
        return $respond(['status' => 403, 'error' => 'Invalid API key (stub)'], 403);
    }

    $target = (string)($query['url'] ?? '');
    $theme = (string)($query['theme'] ?? '');
    $frame = add_query_arg(['iframely_e2e_frame' => '1', 'url' => rawurlencode($target)], home_url('/'));
    if ($theme !== '') {
        $frame = add_query_arg('theme', rawurlencode($theme), $frame);
    }

    $html = sprintf(
        '<div class="iframely-e2e-embed" data-url="%s" data-query="%s"><iframe title="Iframely stub embed" src="%s" style="width: 100%%; height: 120px; border: 0;"></iframe></div>',
        esc_attr($target),
        esc_attr((string)wp_json_encode($query)),
        esc_url($frame)
    );

    // In the block editor the real embed reports its options to the page; the
    // plugin turns them into the "Iframely options" panel.
    if (!empty($query['gutenberg'])) {
        $message = [
            'method' => 'setIframelyEmbedOptions',
            'context' => $frame,
            'data' => [
                'query' => $theme !== '' ? ['theme'] : [],
                'theme' => ['value' => $theme === 'dark'],
            ],
        ];
        $html .= sprintf(
            '<script>setTimeout(function () { window.postMessage(%s, "*"); }, 100);</script>',
            wp_json_encode(wp_json_encode($message))
        );
    }

    return $respond([
        'version' => '1.0',
        'type' => 'rich',
        'title' => 'Stub embed',
        'provider_name' => 'Iframely stub',
        'html' => $html,
    ], 200);
}, 10, 3);

/**
 * Stand-in for the page that the real embed iframe would load.
 */
add_action('init', static function () {
    if (!isset($_GET['iframely_e2e_frame'])) {
        return;
    }
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    printf(
        '<!doctype html><title>Iframely stub embed</title><body style="margin: 0; font: 14px sans-serif;"><p class="iframely-e2e-frame" data-theme="%s">Stub embed for %s</p></body>',
        esc_attr(sanitize_text_field(wp_unslash($_GET['theme'] ?? ''))),
        esc_html(sanitize_text_field(wp_unslash($_GET['url'] ?? '')))
    );
    exit;
});

/**
 * Helpers for the tests: put the plugin into a known state, read the API calls.
 */
add_action('rest_api_init', static function () {
    $permission = static function () {
        return current_user_can('manage_options');
    };

    register_rest_route('iframely-e2e/v1', '/reset', [
        'methods' => 'POST',
        'permission_callback' => $permission,
        'callback' => static function (WP_REST_Request $request) {
            global $wpdb;

            // Back to the state right after the plugin was activated for the first time.
            \Iframely\Options::reset();
            delete_option('iframely_e2e_requests');

            // Forget cached embeds, so every test sees its own API calls: editor
            // previews are kept in transients, rendered embeds in post meta and in
            // "oembed_cache" posts.
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_oembed\_%' OR option_name LIKE '\_transient\_timeout\_oembed\_%'");
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_oembed\_%'");
            foreach (get_posts(['post_type' => 'oembed_cache', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $cache_id) {
                wp_delete_post($cache_id, true);
            }
            wp_cache_flush();

            \Iframely\Activation::run();
            \Iframely\Options::setVersion(1);

            $key = (string)$request->get_param('api_key');
            if ($key !== '') {
                \Iframely\Options::setApiKey($key);
            }

            return \Iframely\Options::collect();
        },
    ]);

    register_rest_route('iframely-e2e/v1', '/requests', [
        'methods' => 'GET',
        'permission_callback' => $permission,
        'callback' => static function () {
            return get_option('iframely_e2e_requests', []);
        },
    ]);
});
