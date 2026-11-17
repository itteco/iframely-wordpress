<?php

namespace Iframely\Tests;

use Iframely\Options;
use Iframely\Upgrade;

/**
 * Migration of the settings stored by plugin versions before 1.0.
 */
class UpgradeTest extends TestCase
{
    public function set_up(): void
    {
        parent::set_up();

        // A site that has never run the migration.
        Options::delete('iframely_version');
        Options::delete('iframely_all_set');
    }

    public function test_marks_the_site_as_migrated(): void
    {
        Upgrade::run();

        $this->assertSame(1, Options::getVersion());
    }

    public function test_leaves_new_settings_alone_when_there_is_nothing_to_migrate(): void
    {
        Upgrade::run();

        $this->assertFalse(Options::get('iframely_cache_refresh'));
        $this->assertFalse(Options::get('iframely_cache_ttl'));
        $this->assertFalse(Options::get('iframely_builtins_replace'));
        $this->assertFalse(Options::get('iframely_previews_enhance'));
    }

    public function test_flags_the_site_as_all_set_when_an_api_key_exists(): void
    {
        Upgrade::run();

        $this->assertTrue(Options::isAllSet());
    }

    public function test_does_not_flag_the_site_as_all_set_without_an_api_key(): void
    {
        Options::delete('iframely_api_key');

        Upgrade::run();

        $this->assertFalse(Options::isAllSet());
        $this->assertSame(1, Options::getVersion());
    }

    public function test_does_nothing_once_the_migration_has_run(): void
    {
        Options::setVersion(1);
        Options::update('iframely_only_shortcode', 1);
        Options::update('publish_iframely_cards', 1);
        Options::update('iframely_cache_ttl', 3);

        Upgrade::run();

        $this->assertSame('1', (string)Options::get('iframely_only_shortcode'));
        $this->assertSame('1', (string)Options::get('publish_iframely_cards'));
        $this->assertSame(3, Options::getCacheTtl());
        $this->assertFalse(Options::isAllSet());
    }

    public function test_does_nothing_on_a_site_that_is_already_all_set(): void
    {
        Options::setAllSet(true);
        Options::update('iframely_only_shortcode', 1);

        Upgrade::run();

        $this->assertSame('1', (string)Options::get('iframely_only_shortcode'));
        $this->assertSame(0, Options::getVersion());
    }

    /**
     * The old setting stored the cache lifetime in days; the new one stores seconds
     * and only accepts the presets offered on the settings page.
     *
     * @dataProvider legacyCacheTtlProvider
     *
     * @param int|string $legacyDays
     */
    public function test_converts_the_cache_lifetime_from_days_to_seconds($legacyDays, int $expectedTtl): void
    {
        Options::update('iframely_cache_ttl', $legacyDays);

        Upgrade::run();

        $this->assertTrue(Options::isCacheRefreshEnabled());
        $this->assertSame($expectedTtl, Options::getCacheTtl());
    }

    /**
     * @return array<string, array{0: int|string, 1: int}>
     */
    public function legacyCacheTtlProvider(): array
    {
        return [
            '1 day' => [1, DAY_IN_SECONDS],
            '3 days' => [3, 3 * DAY_IN_SECONDS],
            '7 days is the 1 week preset' => [7, WEEK_IN_SECONDS],
            '14 days, stored as a string' => ['14', 2 * WEEK_IN_SECONDS],
            '30 days is the 1 month preset' => [30, MONTH_IN_SECONDS],
            '10 days is not a preset: falls back to the default' => [10, WEEK_IN_SECONDS],
            '365 days is not a preset: falls back to the default' => [365, WEEK_IN_SECONDS],
        ];
    }

    public function test_the_migration_does_not_use_translations(): void
    {
        // It runs while plugins are loaded. WordPress 6.7 and later log a notice when
        // the translations of a plugin are asked for that early.
        $translated = [];
        $record = static function ($translation, $text, $domain) use (&$translated) {
            if ($domain === 'iframely') {
                $translated[] = $text;
            }
            return $translation;
        };
        add_filter('gettext', $record, 10, 3);
        Options::update('iframely_cache_ttl', 10);

        Upgrade::run();

        remove_filter('gettext', $record, 10);
        $this->assertSame([], $translated);
        $this->assertSame(WEEK_IN_SECONDS, Options::getCacheTtl());
    }

    public function test_a_zero_cache_lifetime_switches_cache_refresh_off(): void
    {
        Options::setCacheRefresh(true);
        Options::update('iframely_cache_ttl', 0);

        Upgrade::run();

        $this->assertFalse(Options::isCacheRefreshEnabled());
        $this->assertSame(0, Options::getCacheTtl());
    }

    public function test_only_shortcode_mode_becomes_builtins_not_replaced(): void
    {
        Options::setBuiltinsReplace(true);
        Options::update('iframely_only_shortcode', 1);

        Upgrade::run();

        $this->assertFalse(Options::isBuiltinsReplaced());
        $this->assertFalse(Options::get('iframely_only_shortcode'), 'The legacy option should be removed.');
    }

    public function test_disabled_only_shortcode_mode_becomes_builtins_replaced(): void
    {
        Options::update('iframely_only_shortcode', 0);

        Upgrade::run();

        $this->assertTrue(Options::isBuiltinsReplaced());
        $this->assertFalse(Options::get('iframely_only_shortcode'), 'The legacy option should be removed.');
    }

    public function test_enabled_summary_cards_become_enhanced_previews(): void
    {
        Options::update('publish_iframely_cards', 1);

        Upgrade::run();

        $this->assertTrue(Options::isPreviewsEnhanced());
        $this->assertFalse(Options::get('publish_iframely_cards'), 'The legacy option should be removed.');
    }

    public function test_disabled_summary_cards_become_previews_not_enhanced(): void
    {
        Options::setPreviewsEnhance(true);
        Options::update('publish_iframely_cards', 0);

        Upgrade::run();

        $this->assertFalse(Options::isPreviewsEnhanced());
        $this->assertFalse(Options::get('publish_iframely_cards'), 'The legacy option should be removed.');
    }
}
