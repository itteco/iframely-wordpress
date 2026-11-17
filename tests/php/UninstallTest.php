<?php

namespace Iframely\Tests;

use Iframely\Options;

/**
 * Deleting the plugin on the Plugins screen runs uninstall.php.
 */
class UninstallTest extends TestCase
{
    private const SETTINGS = [
        'iframely_api_key',
        'iframely_api_params',
        'iframely_cache_refresh',
        'iframely_cache_ttl',
        'iframely_builtins_replace',
        'iframely_previews_enhance',
        'iframely_version',
        'iframely_all_set',
        'iframely_reactivation',
        // From the versions before 1.0.
        'iframely_only_shortcode',
        'publish_iframely_cards',
    ];

    private function uninstall(): void
    {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', IFRAMELY_PLUGIN_FILE);
        }

        require IFRAMELY_PLUGIN_DIR . 'uninstall.php';
    }

    public function test_deleting_the_plugin_removes_its_settings(): void
    {
        foreach (self::SETTINGS as $name) {
            Options::update($name, 'value');
        }
        update_option('blogname', 'Kept');

        $this->uninstall();

        foreach (self::SETTINGS as $name) {
            $this->assertFalse(Options::get($name), $name);
            $this->assertFalse(get_option($name), $name);
        }
        $this->assertSame('Kept', get_option('blogname'));
        $this->assertSame([], $this->optionsNamedAfterThePlugin());
    }

    public function test_deleting_the_plugin_leaves_posts_and_their_embeds_alone(): void
    {
        $content = '<!-- wp:embed {"url":"https://example.com/video?iframely=eyJ0IjoxfQ%3D%3D"} -->';
        $postId = self::factory()->post->create(['post_content' => $content]);
        update_post_meta($postId, '_oembed_0123456789abcdef0123456789abcdef', self::EMBED_HTML);
        update_post_meta($postId, '_oembed_time_0123456789abcdef0123456789abcdef', 1700000000);
        $saved = get_post($postId)->post_content;
        $this->assertStringContainsString('?iframely=eyJ0IjoxfQ%3D%3D', $saved);

        $this->uninstall();

        clean_post_cache($postId);
        $this->assertSame($saved, get_post($postId)->post_content);
        $this->assertSame(self::EMBED_HTML, get_post_meta($postId, '_oembed_0123456789abcdef0123456789abcdef', true));
        $this->assertSame('1700000000', get_post_meta($postId, '_oembed_time_0123456789abcdef0123456789abcdef', true));
    }

    public function test_every_setting_the_plugin_stores_is_on_the_list(): void
    {
        // Whatever the plugin writes, by any of its setters, has to be gone afterwards.
        Options::setApiKey('abc');
        Options::setApiParams('theme=dark');
        Options::setCacheRefresh(true);
        Options::setCacheTtl(WEEK_IN_SECONDS);
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);
        Options::setVersion(1);
        Options::setAllSet(true);
        Options::setReactivationFlag();
        $this->assertNotSame([], $this->optionsNamedAfterThePlugin());

        $this->uninstall();

        $this->assertSame([], $this->optionsNamedAfterThePlugin());
    }

    public function test_the_file_only_runs_for_wordpress_and_is_part_of_the_release(): void
    {
        $source = (string)file_get_contents(IFRAMELY_PLUGIN_DIR . 'uninstall.php');
        $this->assertMatchesRegularExpression("/if \(!defined\('WP_UNINSTALL_PLUGIN'\)\) \{\s+exit;/", $source);

        $this->assertStringContainsString("'uninstall.php'", (string)file_get_contents(IFRAMELY_PLUGIN_DIR . 'gulpfile.js'));
        $this->assertStringNotContainsString('uninstall.php', (string)file_get_contents(IFRAMELY_PLUGIN_DIR . '.distignore'));
    }

    /**
     * @return string[]
     */
    private function optionsNamedAfterThePlugin(): array
    {
        global $wpdb;

        // What is left in the tables themselves, whatever the caches say.
        $like = '%iframely%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
        if (is_multisite()) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above
            $names = array_merge($names, $wpdb->get_col($wpdb->prepare("SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s", $like)));
        }

        // WordPress keeps its own records under other names, e.g. the list of active plugins.
        return array_values(array_filter($names, static function ($name) {
            return strpos($name, 'iframely_') === 0 || $name === 'publish_iframely_cards';
        }));
    }
}
