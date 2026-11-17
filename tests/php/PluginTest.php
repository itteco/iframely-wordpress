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
        $this->assertSame(9, has_filter('pre_oembed_result', [Oembed::class, 'beforeOwnPostLookup']));
        $this->assertSame(11, has_filter('pre_oembed_result', [Oembed::class, 'afterOwnPostLookup']));
        $this->assertSame(10, has_filter('oembed_fetch_url', [Oembed::class, 'maybe_replace_iframe_card']));
        $this->assertSame(20, has_filter('oembed_fetch_url', [Gutenberg::class, 'maybe_add_iframely_url_options']));
        $this->assertSame(10, has_filter('oembed_fetch_url', [Amp::class, 'maybe_add_iframe_amp']));
        $this->assertSame(10, has_filter('embed_oembed_html', [Amp::class, 'iframely_filter_oembed_result']));
        $this->assertSame(10, has_filter('embed_defaults', [Cache::class, 'iframely_embed_defaults']));
        $this->assertSame(99, has_filter('oembed_ttl', [Cache::class, 'updateCacheTtl']));
        $this->assertSame(1, has_filter('the_content', [Feed::class, 'disableInFeeds']));
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
        $userId = self::factory()->user->create(['role' => 'administrator']);
        if (is_multisite()) {
            grant_super_admin($userId);
        }
        wp_set_current_user($userId);
        Settings::register();

        $actions = Plugin::addSettingsLink(['deactivate' => '<a href="#">Deactivate</a>']);

        $this->assertCount(2, $actions);
        $this->assertSame('<a href="#">Deactivate</a>', $actions['deactivate']);
        $this->assertMatchesRegularExpression('#^<a href="http[^"]*page=iframely">Settings</a>$#', end($actions));
    }

    public function test_on_a_network_the_settings_link_goes_to_the_network_admin_from_any_plugins_screen(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('Only relevant on a network.');
        }
        $userId = self::factory()->user->create(['role' => 'administrator']);
        grant_super_admin($userId);
        wp_set_current_user($userId);
        // The plugins screen of one site: the settings page does not exist there.
        $this->assertFalse(is_network_admin());

        $actions = Plugin::addSettingsLink([]);

        $this->assertSame(['<a href="' . esc_url(network_admin_url('settings.php?page=iframely')) . '">Settings</a>'], $actions);
    }

    public function test_on_a_network_site_administrators_get_no_settings_link(): void
    {
        if (!is_multisite()) {
            $this->markTestSkipped('Only relevant on a network.');
        }
        // They cannot open the settings of the network.
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $this->assertSame([], Plugin::addSettingsLink([]));
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

    public function test_a_notice_keeps_links_and_drops_scripts(): void
    {
        ob_start();
        Plugin::notice('Saved. <a href="https://iframely.com/keys" target="_blank">Your keys</a><script>alert(1)</script>', 'error"><script>alert(2)</script>');
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('<a href="https://iframely.com/keys" target="_blank">Your keys</a>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertSame(1, substr_count($html, '<div'));
    }

    public function test_every_view_refuses_to_run_outside_of_wordpress(): void
    {
        $views = array_merge(glob(IFRAMELY_PLUGIN_DIR . 'views/*.php'), glob(IFRAMELY_PLUGIN_DIR . 'views/*/*.php'));

        $this->assertCount(10, $views);
        foreach ($views as $view) {
            $start = substr((string)file_get_contents($view), 0, 60);
            $this->assertMatchesRegularExpression('/^<\?php\s+defined\(\'ABSPATH\'\) \|\| exit;/', $start, basename($view));
        }
    }

    public function test_the_images_of_the_onboarding_exist_under_plain_names(): void
    {
        ob_start();
        Plugin::view('partials/onboarding');
        $html = (string)ob_get_clean();

        preg_match_all('#' . preg_quote(IFRAMELY_PLUGIN_URL, '#') . '(assets/[^\s"]+)#', $html, $matches);
        $images = array_values(array_unique($matches[1]));
        sort($images);

        $this->assertSame(['assets/onboarding-2x.avif', 'assets/onboarding-2x.png', 'assets/onboarding.avif', 'assets/onboarding.png'], $images);
        foreach ($images as $image) {
            $this->assertFileExists(IFRAMELY_PLUGIN_DIR . $image);
            // wordpress.org accepts letters, digits, dots, hyphens and underscores in file names.
            $this->assertMatchesRegularExpression('#^[\w./-]+$#', $image);
        }
    }
}
