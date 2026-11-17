<?php

namespace Iframely\Tests;

use Iframely\Embed\Amp;
use Iframely\Embed\Cache;
use Iframely\Embed\Feed;
use Iframely\Embed\Gutenberg;
use Iframely\Embed\Oembed;
use Iframely\Options;
use Iframely\Plugin;
use Iframely\UI\Settings;

class PluginTest extends TestCase
{
    public function test_plugin_is_loaded_from_its_wordpress_org_directory_name(): void
    {
        $this->assertSame('iframely/iframely.php', IFRAMELY_PLUGIN_FILE);
        $this->assertSame(trailingslashit(dirname(__DIR__, 2)), IFRAMELY_PLUGIN_DIR);
    }

    public function test_version_constant_matches_the_plugin_header(): void
    {
        $header = get_file_data(IFRAMELY_PLUGIN_DIR . 'iframely.php', ['Version' => 'Version']);

        $this->assertSame(IFRAMELY_VERSION, $header['Version']);
    }

    public function test_stable_tag_in_the_readme_matches_the_plugin_version(): void
    {
        $readme = (string)file_get_contents(IFRAMELY_PLUGIN_DIR . 'readme.txt');

        $this->assertMatchesRegularExpression('/^Stable tag: ' . preg_quote(IFRAMELY_VERSION, '/') . '$/m', $readme);
    }

    public function test_readme_and_plugin_header_state_the_same_requirements(): void
    {
        $fields = ['RequiresWP' => 'Requires at least', 'RequiresPHP' => 'Requires PHP'];
        $header = get_file_data(IFRAMELY_PLUGIN_DIR . 'iframely.php', $fields);
        $readme = get_file_data(IFRAMELY_PLUGIN_DIR . 'readme.txt', $fields);

        $this->assertSame('6.6', $header['RequiresWP']);
        $this->assertSame('7.4', $header['RequiresPHP']);
        $this->assertSame($header, $readme);
    }

    public function test_readme_and_plugin_header_state_the_same_license(): void
    {
        $fields = ['License' => 'License', 'LicenseURI' => 'License URI'];
        $header = get_file_data(IFRAMELY_PLUGIN_DIR . 'iframely.php', $fields);
        $readme = get_file_data(IFRAMELY_PLUGIN_DIR . 'readme.txt', $fields);

        $this->assertSame('GPLv2 or later', $header['License']);
        $this->assertSame($header, $readme);
        $this->assertFileExists(IFRAMELY_PLUGIN_DIR . 'LICENSE');
    }

    public function test_plugin_counts_as_activated_only_with_an_api_key(): void
    {
        $this->assertTrue(Plugin::isActivated());

        Options::delete('iframely_api_key');

        $this->assertFalse(Plugin::isActivated());
    }

    public function test_embed_features_are_hooked_up_on_a_site_with_an_api_key(): void
    {
        $this->assertTrue(shortcode_exists('iframely'));
        $this->assertSame(10, has_filter('oembed_providers', [Oembed::class, 'maybe_reverse_oembed_providers']));
        $this->assertSame(PHP_INT_MAX, has_filter('pre_oembed_result', [Oembed::class, 'maybe_remove_wp_self_embeds']));
        $this->assertSame(10, has_filter('oembed_fetch_url', [Oembed::class, 'maybe_replace_iframe_card']));
        $this->assertSame(20, has_filter('oembed_fetch_url', [Gutenberg::class, 'maybe_add_iframely_url_options']));
        $this->assertSame(10, has_filter('oembed_fetch_url', [Amp::class, 'maybe_add_iframe_amp']));
        $this->assertSame(10, has_filter('embed_oembed_html', [Amp::class, 'iframely_filter_oembed_result']));
        $this->assertSame(10, has_filter('embed_defaults', [Cache::class, 'iframely_embed_defaults']));
        $this->assertSame(99, has_filter('oembed_ttl', [Cache::class, 'updateCacheTtl']));
        $this->assertSame(1, has_filter('the_content_feed', [Feed::class, 'iframely_disable_on_feed']));
    }

    public function test_settings_page_is_hooked_up(): void
    {
        $this->assertSame(10, has_action('admin_menu', [Settings::class, 'register']));
        $this->assertSame(10, has_action('network_admin_menu', [Settings::class, 'register']));
        $this->assertSame(10, has_action('admin_init', [Settings::class, 'save']));
        $this->assertSame(10, has_action('admin_enqueue_scripts', [Settings::class, 'enqueue']));
        $this->assertSame(10, has_action('enqueue_block_editor_assets', [Settings::class, 'enqueueEditor']));
    }

    public function test_plugins_screen_gets_a_settings_link(): void
    {
        $this->assertSame(10, has_filter('plugin_action_links_iframely/iframely.php', [Plugin::class, 'addSettingsLink']));
        $this->assertSame(10, has_filter('network_admin_plugin_action_links_iframely/iframely.php', [Plugin::class, 'addSettingsLink']));

        $actions = Plugin::addSettingsLink(['deactivate' => '<a href="#">Deactivate</a>']);

        $this->assertCount(2, $actions);
        $this->assertSame('<a href="#">Deactivate</a>', $actions['deactivate']);
        $this->assertMatchesRegularExpression('#^<a href="[^"]*">Settings</a>$#', end($actions));
    }

    public function test_asset_urls_point_into_the_build_directory(): void
    {
        $this->assertSame(IFRAMELY_PLUGIN_URL . 'build/index.js', Plugin::asset('index.js'));
    }

    public function test_notice_markup(): void
    {
        ob_start();
        Plugin::notice('Something happened.', 'error');
        $dismissible = (string)ob_get_clean();

        ob_start();
        Plugin::notice('Something happened.', 'success', false);
        $fixed = (string)ob_get_clean();

        $this->assertStringContainsString('notice-error', $dismissible);
        $this->assertStringContainsString('is-dismissible', $dismissible);
        $this->assertStringContainsString('Something happened.', $dismissible);
        $this->assertStringContainsString('notice-success', $fixed);
        $this->assertStringNotContainsString('is-dismissible', $fixed);
    }
}
