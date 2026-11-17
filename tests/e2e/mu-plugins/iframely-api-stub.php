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
 * What the real API would make of a URL: a player for the media sites the tests
 * use, a summary card for any other page.
 */
function iframely_e2e_kind(string $url): string
{
    return preg_match('#^https?://(www\.)?(youtube\.com|youtu\.be|vimeo\.com)/#i', $url) ? 'player' : 'card';
}

/**
 * The option parameters of a query that apply to an embed of the given kind.
 */
function iframely_e2e_option_params(string $kind, array $query): array
{
    $names = $kind === 'card' ? ['card'] : ['_cc_load_policy', '_start'];
    $params = [];
    foreach ($names as $name) {
        if (isset($query[$name]) && is_scalar($query[$name]) && (string)$query[$name] !== '') {
            $params[$name] = (string)$query[$name];
        }
    }
    return $params;
}

/**
 * The per-URL options an embed reports, in the format of the real API. The
 * samples were taken from a Wikipedia article (card) and a YouTube video (player).
 */
function iframely_e2e_options(string $kind, array $params): array
{
    if ($kind === 'card') {
        $options = [
            'card' => [
                'value' => ($params['card'] ?? '') === 'small' ? 'small' : '',
                'values' => ['small' => 'Make it a small card'],
            ],
        ];
    } else {
        $options = [
            '_start' => ['label' => 'Start from', 'value' => $params['_start'] ?? '', 'placeholder' => 'ex.: 11, 1m10s'],
            '_cc_load_policy' => [
                'label' => 'Closed captions',
                'value' => in_array($params['_cc_load_policy'] ?? '', ['1', 'true'], true),
            ],
        ];
    }
    if ($params) {
        $options['query'] = array_keys($params);
    }
    return $options;
}

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

    // Like the real API, embed the page itself: the options the plugin appends
    // to the URL arrive as separate parameters.
    $target = remove_query_arg('iframely', (string)($query['url'] ?? ''));
    $kind = iframely_e2e_kind($target);
    $params = iframely_e2e_option_params($kind, $query);
    $small = ($params['card'] ?? '') === 'small';

    $frame = add_query_arg(
        array_map('rawurlencode', ['iframely_e2e_frame' => '1', 'url' => $target] + $params),
        home_url('/')
    );

    // The real embed code is a link that https://iframely.net/embed.js turns into
    // an iframe. The stub goes straight to the iframe, served by this site.
    $html = sprintf(
        '<div class="iframely-e2e-embed" data-kind="%s" data-size="%s" data-url="%s" data-query="%s">'
        . '<iframe title="Iframely stub embed" src="%s" style="width: 100%%; height: %dpx; border: 0;"></iframe></div>',
        esc_attr($kind),
        esc_attr($kind === 'card' ? ($small ? 'small' : 'regular') : ''),
        esc_attr($target),
        esc_attr((string)wp_json_encode($query)),
        esc_url($frame),
        $kind === 'card' ? ($small ? 140 : 320) : 360
    );

    return $respond([
        'version' => '1.0',
        'type' => 'rich',
        'title' => 'Stub embed',
        'provider_name' => 'Iframely stub',
        'html' => $html,
        'options' => iframely_e2e_options($kind, $params),
    ], 200);
}, 10, 3);

/**
 * Stand-in for the page that the real embed iframe loads. Like the real one, it
 * reports the options of the embed to its parent window. In the block editor
 * the plugin relays that message to the editor and builds the options panel
 * from it.
 */
add_action('init', static function () {
    if (!isset($_GET['iframely_e2e_frame'])) {
        return;
    }

    $query = array_map('sanitize_text_field', wp_unslash($_GET));
    $target = (string)($query['url'] ?? '');
    $kind = iframely_e2e_kind($target);
    $params = iframely_e2e_option_params($kind, $query);
    $message = [
        'method' => 'setIframelyEmbedOptions',
        'data' => iframely_e2e_options($kind, $params),
        'url' => $target,
    ];

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    printf(
        '<!doctype html><title>Iframely stub embed</title><body style="margin: 0; font: 14px sans-serif;">'
        . '<p class="iframely-e2e-frame" data-kind="%s" data-params="%s">Stub %s for %s</p>'
        . '<script>var message = %s; message.context = window.location.href;'
        . 'window.parent.postMessage(JSON.stringify(message), "*");</script></body>',
        esc_attr($kind),
        esc_attr((string)wp_json_encode((object)$params)),
        esc_html($kind),
        esc_html($target),
        wp_json_encode($message)
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
