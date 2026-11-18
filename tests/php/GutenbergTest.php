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

    public function test_numbers_are_sent_as_strings_and_checkboxes_as_true_and_false(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['start' => 70, 'ratio' => 1.5, 'captions' => true, '_compact' => false]);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []));

        $this->assertSame('70', $query['start']);
        $this->assertSame('1.5', $query['ratio']);
        $this->assertSame('true', $query['captions']);
        // An option that is on by default can only be turned off by sending "false".
        $this->assertSame('false', $query['_compact']);
    }

    public function test_option_values_are_encoded(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['title' => 'Tom & Jerry #1 = 100%', '_start' => 'a&api_key=other']);

        $provider = Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []);

        $this->assertSame(
            self::PROVIDER . '&title=Tom+%26+Jerry+%231+%3D+100%25&_start=a%26api_key%3Dother',
            $provider
        );
        $this->assertSame('k', self::queryOf($provider)['api_key']);
    }

    public function test_option_values_stay_encoded_when_wordpress_adds_its_own_parameters_later(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['_start' => 'a&api_key=other#x']);

        $provider = Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []);
        $query = self::queryOf(add_query_arg('format', 'json', $provider));

        $this->assertSame('a&api_key=other#x', $query['_start']);
        $this->assertSame('k', $query['api_key']);
        $this->assertSame('json', $query['format']);
    }

    public function test_options_cannot_replace_who_asks_for_which_page_and_in_which_format(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions([
            'api_key' => 'other',
            'key' => 'other',
            'origin' => 'other.example',
            'url' => 'https://other.example/',
            'uri' => 'https://other.example/',
            'format' => 'xml',
            'callback' => 'alert',
            'API_KEY' => 'other',
            'theme' => 'dark',
        ]);

        $provider = Gutenberg::maybe_add_iframely_url_options(self::PROVIDER . '&url=' . rawurlencode(self::URL), $url, []);

        $this->assertSame(
            ['origin' => 'example.org', 'api_key' => 'k', 'url' => self::URL, 'theme' => 'dark'],
            self::queryOf($provider)
        );
    }

    public function test_options_with_unusual_names_or_values_that_are_not_plain_are_left_out(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions([
            'a&api_key' => 'other',
            'b=c' => '1',
            'd[e]' => '1',
            '' => '1',
            'list' => ['api_key' => 'other'],
            'nothing' => null,
            '_theme' => 'dark',
            'click-to.play' => '1',
        ]);

        $provider = Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, $url, []);

        $this->assertSame(self::PROVIDER . '&_theme=dark&click-to.play=1', $provider);
    }

    public function test_options_whose_base64_form_contains_a_plus_are_added(): void
    {
        $encoded = self::encodeUrlOptions(['t' => 'xy>']);
        $this->assertStringContainsString('%2B', $encoded, 'The sample must produce a "+" in base64.');

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, self::URL . '?iframely=' . $encoded, []));

        $this->assertSame('xy>', $query['t']);
    }

    public function test_options_are_read_when_the_plus_of_their_base64_form_was_not_encoded(): void
    {
        $encoded = str_replace('%2B', '+', self::encodeUrlOptions(['t' => 'xy>']));
        $this->assertStringContainsString('+', $encoded);

        $query = self::queryOf(Gutenberg::maybe_add_iframely_url_options(self::PROVIDER, self::URL . '?iframely=' . $encoded, []));

        $this->assertSame('xy>', $query['t']);
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
            'options that are a number' => [self::PROVIDER, self::URL . '?iframely=' . rawurlencode(base64_encode('5'))],
            'options that are a text' => [self::PROVIDER, self::URL . '?iframely=' . rawurlencode(base64_encode('"dark"'))],
            'options that are a list' => [self::PROVIDER, self::URL . '?iframely=' . rawurlencode(base64_encode('["api_key","other"]'))],
            'options given more than once' => [self::PROVIDER, self::URL . '?iframely[]=' . $options . '&iframely[]=' . $options],
            'no options at all' => [self::PROVIDER, self::URL . '?iframely='],
        ];
    }

    /**
     * @dataProvider urlWithoutOptionsProvider
     */
    public function test_the_options_can_be_taken_out_of_a_url(string $url, string $expected): void
    {
        $this->assertSame($expected, Gutenberg::removeUrlOptions($url));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function urlWithoutOptionsProvider(): array
    {
        return [
            'the only parameter' => ['https://example.com/video?iframely=eyJ0IjoxfQ%3D%3D', 'https://example.com/video'],
            'the last parameter' => ['https://example.com/watch?v=42&iframely=eyJ0IjoxfQ%3D%3D', 'https://example.com/watch?v=42'],
            'the first parameter' => ['https://example.com/watch?iframely=eyJ0IjoxfQ%3D%3D&v=42', 'https://example.com/watch?v=42'],
            'in the middle' => ['https://example.com/watch?a=1&iframely=eyJ0IjoxfQ%3D%3D&v=4%202', 'https://example.com/watch?a=1&v=4%202'],
            'before a fragment' => ['https://example.com/watch?v=42&iframely=eyJ0IjoxfQ%3D%3D#t=10', 'https://example.com/watch?v=42#t=10'],
            'the only parameter, before a fragment' => ['https://example.com/video?iframely=eyJ0IjoxfQ%3D%3D#top', 'https://example.com/video#top'],
            'no options' => ['https://example.com/watch?v=42&myiframely=1', 'https://example.com/watch?v=42&myiframely=1'],
            'no parameters' => ['https://example.com/video', 'https://example.com/video'],
        ];
    }

    public function test_a_url_that_cannot_be_embedded_is_shown_as_a_link_without_the_options(): void
    {
        $this->respondWithError();
        $url = 'https://example.com/watch?v=42&iframely=' . self::encodeUrlOptions(['card' => 'small']);

        $html = $GLOBALS['wp_embed']->run_shortcode('[embed]' . $url . '[/embed]');

        $this->assertSame('<a href="https://example.com/watch?v=42">https://example.com/watch?v=42</a>', $html);
        // The request itself was made with the options.
        $this->assertSame('small', self::queryOf($this->iframelyRequests()[0])['card']);
    }

    public function test_only_the_link_that_wordpress_makes_is_changed(): void
    {
        $url = self::URL . '?iframely=' . self::encodeUrlOptions(['card' => 'small']);
        $embed = '<iframe src="https://cdn.example/?u=' . esc_attr($url) . '"></iframe>';

        $this->assertSame(self::URL, Gutenberg::removeUrlOptionsFromLink($url, $url));
        $this->assertSame($embed, Gutenberg::removeUrlOptionsFromLink($embed, $url));
        $this->assertFalse(Gutenberg::removeUrlOptionsFromLink(false, $url));
        $link = '<a href="' . self::URL . '">' . self::URL . '</a>';
        $this->assertSame($link, Gutenberg::removeUrlOptionsFromLink($link, self::URL));
    }

    public function test_setting_up_registers_the_same_hooks_for_everyone(): void
    {
        wp_set_current_user(0);
        Gutenberg::init();

        $this->assertSame(20, has_filter('oembed_fetch_url', [Gutenberg::class, 'maybe_add_iframely_url_options']));
        $this->assertSame(20, has_filter('embed_maybe_make_link', [Gutenberg::class, 'removeUrlOptionsFromLink']));
        $this->assertSame(10, has_filter('rest_request_before_callbacks', [Gutenberg::class, 'startEditorPreview']));
        $this->assertSame(10, has_filter('rest_request_after_callbacks', [Gutenberg::class, 'endEditorPreview']));
        $this->assertSame(10, has_action('enqueue_block_editor_assets', [Gutenberg::class, 'iframely_gutenberg_loader']));
    }

    public function test_setting_up_does_not_ask_who_the_user_is(): void
    {
        // Asking on plugins_loaded settles the user before plugins that sign users in
        // their own way have registered, and leaves their visitors signed out.
        $asked = 0;
        $count = static function ($userId) use (&$asked) {
            $asked++;
            return $userId;
        };
        add_filter('determine_current_user', $count);
        $GLOBALS['current_user'] = null;

        Gutenberg::init();

        remove_filter('determine_current_user', $count);
        $this->assertSame(0, $asked);
        $this->assertNull($GLOBALS['current_user']);
    }

    public function test_the_preview_mode_is_off_outside_of_a_preview_request(): void
    {
        Gutenberg::init();

        $this->assertFalse(has_filter('oembed_result', [Gutenberg::class, 'inject_events_proxy_to_gutenberg']));
        $this->assertFalse(has_filter('oembed_fetch_url', [Gutenberg::class, 'maybe_add_gutenberg_1']));
        $this->assertFalse(has_filter('embed_defaults', [Gutenberg::class, 'iframely_bust_gutenberg_cache']));
        $this->assertFalse(has_filter('oembed_request_post_id', [Gutenberg::class, 'maybe_remove_wp_self_embeds_in_guttenberg']));
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
        $this->assertStringContainsString('<script type="text/javascript">window.addEventListener("message",', $html);
        // Messages are passed on from the frames of the embed only, and to the editor only.
        $this->assertStringContainsString('contentWindow===e.source', $html);
        $this->assertStringContainsString('window.top.postMessage(e.data,o&&o!=="null"?o:"*")', $html);

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

    public function test_the_editor_script_declares_what_its_build_and_its_code_need(): void
    {
        Gutenberg::iframely_gutenberg_loader();

        $script = wp_scripts()->registered['iframely-gutenberg'];
        $asset = require IFRAMELY_PLUGIN_DIR . 'build/index.asset.php';

        $this->assertContains('react-jsx-runtime', $asset['dependencies'], 'The build is expected to need the JSX runtime.');
        $this->assertSame([], array_values(array_diff($asset['dependencies'], $script->deps)));
        // The code also uses jQuery and the two scripts of Iframely's CDN.
        $this->assertSame(['jquery', 'iframely-embed', 'iframely-options'], array_values(array_diff($script->deps, $asset['dependencies'])));
        // A new build gets a new address, so browsers do not keep the old one.
        $this->assertSame($asset['version'], $script->ver);
    }

    public function test_the_texts_of_the_editor_script_can_be_translated(): void
    {
        Gutenberg::iframely_gutenberg_loader();

        $this->assertSame('iframely', wp_scripts()->registered['iframely-gutenberg']->textdomain);
    }

    private function preview(array $params = []): \WP_REST_Response
    {
        $request = new WP_REST_Request('GET', '/oembed/1.0/proxy');
        $request->set_query_params($params + ['url' => self::URL]);

        return rest_get_server()->dispatch($request);
    }

    public function test_an_editor_preview_request_reaches_iframely_marked_as_such(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->respondWithEmbed();

        $response = $this->preview();

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

        // The mode ends with the request.
        $this->test_the_preview_mode_is_off_outside_of_a_preview_request();
    }

    public function test_a_preview_is_as_wide_as_the_theme_content_unless_the_editor_asks_for_a_width(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->respondWithEmbed();

        $this->preview();
        $GLOBALS['content_width'] = 840;
        $this->preview();
        $this->preview(['maxwidth' => 320]);

        $widths = array_map(static function ($url) {
            return self::queryOf($url)['maxwidth'];
        }, $this->iframelyRequests());
        $this->assertSame(['600', '840', '320'], $widths);
    }

    public function test_a_preview_is_refused_to_users_who_cannot_edit_posts(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        $this->respondWithEmbed();

        $response = $this->preview();

        $this->assertSame(403, $response->get_status());
        $this->assertCount(0, $this->iframelyRequests());
        $this->test_the_preview_mode_is_off_outside_of_a_preview_request();
    }

    /**
     * The editor loads posts over REST as well. Their embeds are the ones of the
     * site, not previews: without the script and the page-wide style, and from the
     * same cache as the front end.
     */
    public function test_a_post_read_over_rest_by_an_editor_has_the_embeds_of_the_site(): void
    {
        $postId = self::factory()->post->create(['post_content' => "Before\n\n" . self::URL . "\n\nAfter"]);
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->respondWithEmbed();
        $read = static function () use ($postId) {
            return rest_get_server()->dispatch(new WP_REST_Request('GET', '/wp/v2/posts/' . $postId))->get_data()['content']['rendered'];
        };

        $before = $read();
        $this->assertSame(200, $this->preview()->get_status());
        $after = $read();

        foreach ([$before, $after] as $content) {
            $this->assertStringContainsString(self::EMBED_HTML, $content);
            $this->assertStringNotContainsString('postMessage', $content);
            $this->assertStringNotContainsString('overflow', $content);
        }

        // One request for the post, one for the preview, and one cache entry in the post.
        $requests = array_map([self::class, 'queryOf'], $this->iframelyRequests());
        $this->assertCount(2, $requests);
        $this->assertArrayNotHasKey('gutenberg', $requests[0]);
        $this->assertSame('2', $requests[1]['gutenberg']);
        $cached = array_filter(array_keys(get_post_meta($postId)), static function ($key) {
            return strpos($key, '_oembed_') === 0 && strpos($key, '_oembed_time_') !== 0;
        });
        $this->assertCount(1, $cached);
    }
}
