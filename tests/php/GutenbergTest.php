<?php

namespace Iframely\Tests;

use Iframely\Embed\Gutenberg;
use Iframely\Options;
use WP_REST_Request;

/**
 * Block editor integration on the PHP side: per-embed options carried in the URL,
 * and the extra handling of preview requests made by the editor.
 */
class GutenbergTest extends TestCase
{
    private const PROVIDER = self::ENDPOINT . '?origin=example.org&api_key=k';
    private const URL = 'https://example.com/video';

    public function tear_down(): void
    {
        unset($GLOBALS['content_width']);
        $GLOBALS['wp_rest_server'] = null;
        $GLOBALS['wp_scripts'] = null;

        parent::tear_down();
    }

    public function test_options_encoded_in_the_url_are_added_to_the_api_request(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['theme' => 'dark', '_start' => '1m10s']);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []));

        $this->assertSame('dark', $query['theme']);
        $this->assertSame('1m10s', $query['_start']);
        $this->assertSame('k', $query['api_key']);
        $this->assertSame('example.org', $query['origin']);
    }

    public function test_options_are_found_after_other_query_parameters(): void
    {
        $url = self::URL . '?v=42&iframely=' . self::encodeUrlOptions(['theme' => 'dark']);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []));

        $this->assertSame('dark', $query['theme']);
    }

    public function test_numbers_and_true_are_sent_as_strings_and_false_is_left_out(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['start' => 70, 'captions' => true, 'autoplay' => false]);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []));

        $this->assertSame('70', $query['start']);
        $this->assertSame('1', $query['captions']);
        $this->assertArrayNotHasKey('autoplay', $query);
    }

    /**
     * @dataProvider untouchedProvider
     */
    public function test_provider_is_left_alone(string $provider, string $url): void
    {
        $this->assertSame($provider, Gutenberg::maybe_add_iframely_url_options($provider, $url, []));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function untouchedProvider(): array
    {
        $options = self::encodeUrlOptions(['theme' => 'dark']);

        return [
            'URL without options' => [self::PROVIDER, self::URL . '?v=42'],
            'another oEmbed provider' => ['https://www.youtube.com/oembed', self::URL . '?iframely=' . $options],
            'legacy "iframely=less" marker from old posts' => [self::PROVIDER, self::URL . '?iframely=less'],
            'options that are not valid JSON' => [self::PROVIDER, self::URL . '?iframely=' . rawurlencode(base64_encode('{broken'))],
        ];
    }

    /**
     * Known issue, kept as a record of current behaviour (see the review report):
     * options come from post content, yet they may replace the site's own API key
     * and origin in the request.
     */
    public function test_known_issue_url_options_can_override_api_key_and_origin(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['api_key' => 'other', 'origin' => 'other.example']);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []));

        $this->assertSame('other', $query['api_key']);
        $this->assertSame('other.example', $query['origin']);
    }

    /**
     * Known issue, kept as a record of current behaviour (see the review report):
     * the value is URL-decoded twice, which turns a "+" of the base64 alphabet into
     * a space and corrupts the JSON. All options of that embed are then dropped.
     */
    public function test_known_issue_options_whose_base64_form_contains_a_plus_are_dropped(): void
    {
        $encoded = self::encodeUrlOptions(['t' => 'xy>']);
        $this->assertStringContainsString('%2B', $encoded, 'The sample must produce a "+" in base64.');

        $result = Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, self::URL . '?iframely=' . $encoded, []);

        $this->assertSame(self::PROVIDER, $result);
    }

    public function test_url_options_are_always_processed_but_editor_features_need_edit_rights(): void
    {
        $this->assertSame(20, has_filter('oembed_fetch_url', [Gutenberg::class, 'maybe_add_iframely_url_options']));

        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        Gutenberg::init();

        $this->assertFalse(has_filter('oembed_default_width', [Gutenberg::class, 'iframely_flag_ajax_oembed']));
        $this->assertFalse(has_action('enqueue_block_editor_assets', [Gutenberg::class, 'iframely_gutenberg_loader']));
    }

    public function test_editor_features_are_enabled_for_users_who_can_edit_posts(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'author']));
        Gutenberg::init();

        $this->assertSame(10, has_filter('oembed_default_width', [Gutenberg::class, 'iframely_flag_ajax_oembed']));
        $this->assertSame(10, has_filter('oembed_request_post_id', [Gutenberg::class, 'maybe_remove_wp_self_embeds_in_guttenberg']));
        $this->assertSame(10, has_action('enqueue_block_editor_assets', [Gutenberg::class, 'iframely_gutenberg_loader']));
    }

    public function test_preview_requests_use_the_theme_content_width(): void
    {
        $this->assertSame(600, Gutenberg::iframely_flag_ajax_oembed(600));

        $GLOBALS['content_width'] = 840;

        $this->assertSame(840, Gutenberg::iframely_flag_ajax_oembed(600));
    }

    public function test_preview_requests_get_their_own_cache_key(): void
    {
        $this->assertSame(['width' => 500, 'gutenberg' => 2], Gutenberg::iframely_bust_gutenberg_cache(['width' => 500]));
    }

    public function test_preview_requests_ask_iframely_for_an_iframe_without_embed_js(): void
    {
        $query = self::queryOf(Gutenberg::maybe_add_gutenberg_1(self::PROVIDER, self::URL, []));

        $this->assertSame('1', $query['iframe']);
        $this->assertSame('0', $query['import']);
        $this->assertSame('1', $query['ssl']);
        $this->assertSame('2', $query['gutenberg']);

        $other = 'https://www.youtube.com/oembed';
        $this->assertSame($other, Gutenberg::maybe_add_gutenberg_1($other, self::URL, []));
    }

    public function test_preview_html_gets_the_script_that_relays_messages_to_the_editor(): void
    {
        $html = Gutenberg::inject_events_proxy_to_gutenberg(self::EMBED_HTML, self::URL, []);

        $this->assertStringStartsWith(self::EMBED_HTML, $html);
        $this->assertStringContainsString('window.top.postMessage(e.data,"*")', $html);

        $this->assertSame('  ', Gutenberg::inject_events_proxy_to_gutenberg('  ', self::URL, []));
    }

    public function test_own_posts_are_previewed_by_iframely_only_when_previews_are_enhanced(): void
    {
        Options::setPreviewsEnhance(true);
        $this->assertFalse(Gutenberg::maybe_remove_wp_self_embeds_in_guttenberg(123, self::URL));

        Options::setPreviewsEnhance(false);
        $this->assertSame(123, Gutenberg::maybe_remove_wp_self_embeds_in_guttenberg(123, self::URL));
    }

    public function test_editor_scripts_are_enqueued(): void
    {
        Gutenberg::iframely_gutenberg_loader();

        $this->assertTrue(wp_script_is('iframely-gutenberg', 'enqueued'));
        $this->assertTrue(wp_script_is('iframely-embed', 'enqueued'));
        $this->assertTrue(wp_script_is('iframely-options', 'enqueued'));

        $scripts = wp_scripts()->registered;
        $this->assertSame(IFRAMELY_PLUGIN_URL . 'build/index.js', $scripts['iframely-gutenberg']->src);
        $this->assertSame('https://if-cdn.com/embed.js', $scripts['iframely-embed']->src);
        $this->assertSame('https://if-cdn.com/options.js', $scripts['iframely-options']->src);
        $this->assertSame(['jquery'], $scripts['iframely-options']->deps);
    }

    /**
     * Known issue, kept as a record of current behaviour (see the review report):
     * the dependency list is hardcoded instead of read from build/index.asset.php,
     * and has fallen behind what the build needs.
     */
    public function test_known_issue_editor_script_misses_a_dependency_of_its_build(): void
    {
        Gutenberg::iframely_gutenberg_loader();

        $declared = wp_scripts()->registered['iframely-gutenberg']->deps;
        $needed = (require IFRAMELY_PLUGIN_DIR . 'build/index.asset.php')['dependencies'];

        $this->assertSame(['react-jsx-runtime'], array_values(array_diff($needed, $declared)));
    }

    public function test_an_editor_preview_request_reaches_iframely_marked_as_such(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        Gutenberg::init();
        $this->respondWithEmbed();

        // Registering the REST routes evaluates the oembed_default_width filter;
        // that is the signal the plugin uses to recognise an editor request.
        $GLOBALS['wp_rest_server'] = null;
        $request = new WP_REST_Request('GET', '/oembed/1.0/proxy');
        $request->set_param('url', self::URL);
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $html = $response->get_data()->html;
        $this->assertStringStartsWith(self::EMBED_HTML, $html);
        $this->assertStringContainsString('window.top.postMessage', $html);

        $this->assertCount(1, $this->iframelyRequests());
        $query = self::queryOf($this->iframelyRequests()[0]);
        $this->assertSame(self::URL, $query['url']);
        $this->assertSame('2', $query['gutenberg']);
        $this->assertSame('1', $query['iframe']);
        $this->assertSame('0', $query['import']);
    }
}
