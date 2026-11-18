<?php

namespace Iframely\Tests;

use Iframely\Embed\Oembed;
use WP_Error;
use WP_oEmbed;
use WP_UnitTestCase;

/**
 * Base class for the plugin's integration tests.
 *
 * Every HTTP request made through the WordPress HTTP API is intercepted. A test
 * that expects a request must say what the response is (respondWith*()); any other
 * request fails the test, so nothing can reach the real Iframely API by accident.
 */
abstract class TestCase extends WP_UnitTestCase
{
    public const API_KEY = IFRAMELY_TESTS_API_KEY;
    public const ENDPOINT = 'https://iframe.ly/api/oembed';
    public const CATCH_ALL_PROVIDER = '#https?://[^\s]+#i';
    public const SHORT_LINK_PROVIDER = '#https?://iframe\.ly/.+#i';
    public const EMBED_HTML = '<div class="iframely-test-embed"><iframe title="Test embed" src="https://cdn.iframe.ly/test"></iframe></div>';

    /**
     * Requests intercepted during the current test.
     *
     * @var array<int, array{url: string, args: array<string, mixed>}>
     */
    protected $httpRequests = [];

    /**
     * @var string[]
     */
    private $unexpectedRequests = [];

    /**
     * @var callable|null
     */
    private $httpResponder;

    /**
     * @var array<string, array{0: string, 1: bool}>
     */
    private $oembedProviders = [];

    public function set_up(): void
    {
        parent::set_up();

        $this->httpRequests = [];
        $this->unexpectedRequests = [];
        $this->httpResponder = null;
        // Providers live in a singleton that WordPress does not reset between tests.
        $this->oembedProviders = _wp_oembed_get_object()->providers;

        add_filter('pre_http_request', [$this, 'interceptHttpRequest'], 10, 3);
    }

    public function tear_down(): void
    {
        _wp_oembed_get_object()->providers = $this->oembedProviders;
        $_GET = [];
        $_POST = [];

        parent::tear_down();
    }

    public function assert_post_conditions(): void
    {
        parent::assert_post_conditions();

        $this->assertSame([], $this->unexpectedRequests, 'The test made HTTP requests it did not declare a response for.');
    }

    /**
     * Callback for the pre_http_request filter.
     *
     * @param mixed                $preempt
     * @param array<string, mixed> $args
     * @param string               $url
     * @return array<string, mixed>|WP_Error
     */
    public function interceptHttpRequest($preempt, $args, $url)
    {
        $this->httpRequests[] = ['url' => $url, 'args' => $args];

        if ($this->httpResponder === null) {
            $this->unexpectedRequests[] = $url;
            return new WP_Error('unexpected_http_request', 'Unexpected HTTP request to ' . $url);
        }

        return call_user_func($this->httpResponder, $url, $args);
    }

    /**
     * Answers every HTTP request with the result of $responder($url, $args).
     */
    protected function respondWith(callable $responder): void
    {
        $this->httpResponder = $responder;
    }

    /**
     * Answers every HTTP request with the given JSON document.
     *
     * @param array<string, mixed> $data
     */
    protected function respondWithJson(array $data, int $code = 200): void
    {
        $this->respondWith(static function () use ($data, $code) {
            return self::httpResponse((string)wp_json_encode($data), $code);
        });
    }

    /**
     * Answers every HTTP request with a successful oEmbed response.
     */
    protected function respondWithEmbed(string $html = self::EMBED_HTML): void
    {
        $this->respondWithJson([
            'version' => '1.0',
            'type' => 'rich',
            'title' => 'Test embed',
            'html' => $html,
        ]);
    }

    /**
     * Answers every HTTP request with a transport error.
     */
    protected function respondWithError(): void
    {
        $this->respondWith(static function () {
            return new WP_Error('http_request_failed', 'Could not resolve host');
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected static function httpResponse(string $body, int $code = 200): array
    {
        return [
            'headers' => [],
            'body' => $body,
            'response' => ['code' => $code, 'message' => get_status_header_desc($code)],
            'cookies' => [],
            'filename' => null,
        ];
    }

    /**
     * URLs of all intercepted requests.
     *
     * @return string[]
     */
    protected function requestedUrls(): array
    {
        return wp_list_pluck($this->httpRequests, 'url');
    }

    /**
     * URLs of the intercepted requests that went to the Iframely API.
     *
     * @return string[]
     */
    protected function iframelyRequests(): array
    {
        return array_values(array_filter($this->requestedUrls(), static function ($url) {
            return strpos($url, self::ENDPOINT) === 0;
        }));
    }

    /**
     * Decoded query-string parameters of a URL.
     *
     * @return array<string, mixed>
     */
    protected static function queryOf(string $url): array
    {
        $query = [];
        wp_parse_str((string)wp_parse_url($url, PHP_URL_QUERY), $query);
        return $query;
    }

    /**
     * Builds a WP_oEmbed instance the way WordPress does on a real request.
     *
     * The plugin registers its providers while it is loaded, i.e. before the
     * plugins_loaded action. WordPress queues such providers and applies them, and
     * the oembed_providers filter, once: when WP_oEmbed is constructed. In a test
     * that moment is long gone, so it is replayed here with the current options.
     */
    protected function freshOembed(): WP_oEmbed
    {
        global $wp_actions;

        $fired = $wp_actions['plugins_loaded'];
        unset($wp_actions['plugins_loaded']);
        try {
            Oembed::run();
        } finally {
            $wp_actions['plugins_loaded'] = $fired;
        }

        return new WP_oEmbed();
    }

    /**
     * Encodes embed options the way src/js/utils.js does:
     * encodeURIComponent(window.btoa(JSON.stringify(query))).
     *
     * @param array<string, mixed> $options
     */
    protected static function encodeUrlOptions(array $options): string
    {
        return rawurlencode(base64_encode((string)wp_json_encode($options)));
    }

    /**
     * Makes the given post the current one, as inside the loop.
     */
    protected function enterPost(int $postId): void
    {
        $GLOBALS['post'] = get_post($postId);
        setup_postdata($GLOBALS['post']);
        $GLOBALS['wp_embed']->post_ID = $postId;
    }
}
