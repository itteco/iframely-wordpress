<?php

namespace Iframely\Tests;

use Iframely\Options;

class OptionsTest extends TestCase
{
    public function test_generic_accessors_round_trip(): void
    {
        $this->assertFalse(Options::get('iframely_test_option'));

        $this->assertTrue(Options::update('iframely_test_option', 'value'));
        $this->assertSame('value', Options::get('iframely_test_option'));

        $this->assertTrue(Options::delete('iframely_test_option'));
        $this->assertFalse(Options::get('iframely_test_option'));
    }

    public function test_options_are_stored_site_wide(): void
    {
        Options::update('iframely_test_option', 'value');

        $this->assertSame('value', get_site_option('iframely_test_option'));

        if (is_multisite()) {
            $this->assertFalse(
                get_option('iframely_test_option'),
                'On multisite the value belongs to the network, not to a single site.'
            );
        } else {
            $this->assertSame('value', get_option('iframely_test_option'));
        }
    }

    public function test_options_are_shared_by_all_sites_of_a_network(): void
    {
        $this->skipWithoutMultisite();

        Options::setApiParams('theme=dark');

        $blogId = self::factory()->blog->create();
        switch_to_blog($blogId);
        $key = Options::getApiKey();
        $params = Options::getApiParams();
        restore_current_blog();

        $this->assertSame(self::API_KEY, $key);
        $this->assertSame('theme=dark', $params);
    }

    public function test_typed_getters_have_safe_defaults_when_nothing_is_stored(): void
    {
        Options::reset();

        $this->assertSame('', Options::getApiKey());
        $this->assertSame('', Options::getApiParams());
        $this->assertFalse(Options::getCacheRefresh());
        $this->assertSame(0, Options::getCacheTtl());
        $this->assertFalse(Options::getBuiltinsReplace());
        $this->assertFalse(Options::getPreviewsEnhance());
        $this->assertSame(0, Options::getVersion());
        $this->assertFalse(Options::getAllSet());
        $this->assertFalse(Options::getReactivationFlag());
    }

    public function test_typed_setters_round_trip(): void
    {
        Options::setApiKey('abc123');
        Options::setApiParams('iframe=card');
        Options::setCacheRefresh(true);
        Options::setCacheTtl(WEEK_IN_SECONDS);
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);
        Options::setVersion(1);
        Options::setAllSet(true);

        $this->assertSame('abc123', Options::getApiKey());
        $this->assertSame('iframe=card', Options::getApiParams());
        $this->assertTrue(Options::getCacheRefresh());
        $this->assertTrue(Options::isCacheRefreshEnabled());
        $this->assertSame(WEEK_IN_SECONDS, Options::getCacheTtl());
        $this->assertTrue(Options::getBuiltinsReplace());
        $this->assertTrue(Options::isBuiltinsReplaced());
        $this->assertTrue(Options::getPreviewsEnhance());
        $this->assertTrue(Options::isPreviewsEnhanced());
        $this->assertSame(1, Options::getVersion());
        $this->assertTrue(Options::getAllSet());
        $this->assertTrue(Options::isAllSet());
    }

    public function test_boolean_options_can_be_switched_off_again(): void
    {
        Options::setCacheRefresh(true);
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);

        Options::setCacheRefresh(false);
        Options::setBuiltinsReplace(false);
        Options::setPreviewsEnhance(false);

        $this->assertFalse(Options::getCacheRefresh());
        $this->assertFalse(Options::getBuiltinsReplace());
        $this->assertFalse(Options::getPreviewsEnhance());
    }

    public function test_cache_ttl_can_be_filtered(): void
    {
        Options::setCacheTtl(WEEK_IN_SECONDS);

        add_filter('iframely_cache_ttl', static function ($ttl) {
            return $ttl * 2;
        });

        $this->assertSame(2 * WEEK_IN_SECONDS, Options::getCacheTtl());
    }

    public function test_reactivation_flag_can_be_set_and_cleared(): void
    {
        Options::setReactivationFlag();
        $this->assertTrue(Options::getReactivationFlag());

        Options::deleteReactivationFlag();
        $this->assertFalse(Options::getReactivationFlag());
    }

    public function test_collect_returns_the_settings_shown_on_the_settings_page(): void
    {
        Options::setApiParams('theme=dark');
        Options::setCacheRefresh(true);
        Options::setCacheTtl(DAY_IN_SECONDS);
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(false);

        $this->assertSame([
            'api_key' => self::API_KEY,
            'api_params' => 'theme=dark',
            'cache_refresh' => true,
            'cache_ttl' => DAY_IN_SECONDS,
            'builtins_replace' => true,
            'previews_enhance' => false,
        ], Options::collect());
    }

    public function test_reset_removes_every_plugin_option(): void
    {
        Options::setApiParams('theme=dark');
        Options::setCacheRefresh(true);
        Options::setCacheTtl(DAY_IN_SECONDS);
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);
        Options::setReactivationFlag();

        Options::reset();

        $names = [
            'iframely_api_key',
            'iframely_api_params',
            'iframely_cache_refresh',
            'iframely_cache_ttl',
            'iframely_builtins_replace',
            'iframely_previews_enhance',
            'iframely_version',
            'iframely_all_set',
            'iframely_reactivation',
        ];
        foreach ($names as $name) {
            $this->assertFalse(Options::get($name), $name . ' should have been deleted.');
        }
    }
}
