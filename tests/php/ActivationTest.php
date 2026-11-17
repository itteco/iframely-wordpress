<?php

namespace Iframely\Tests;

use Iframely\Activation;
use Iframely\Options;

/**
 * Defaults written by the plugin activation hook.
 */
class ActivationTest extends TestCase
{
    private const SETTINGS = [
        'iframely_builtins_replace',
        'iframely_previews_enhance',
        'iframely_cache_refresh',
        'iframely_cache_ttl',
    ];

    public function test_a_first_activation_writes_the_default_settings(): void
    {
        Options::reset();

        Activation::run();

        $this->assertTrue(Options::isBuiltinsReplaced());
        $this->assertTrue(Options::isPreviewsEnhanced());
        $this->assertTrue(Options::isCacheRefreshEnabled());
        $this->assertSame(WEEK_IN_SECONDS, Options::getCacheTtl());
        $this->assertTrue(Options::isAllSet());
        $this->assertSame('', Options::getApiKey(), 'Activation must not invent an API key.');
    }

    public function test_activation_keeps_the_settings_of_a_site_that_has_an_api_key(): void
    {
        Options::delete('iframely_all_set');

        Activation::run();

        foreach (self::SETTINGS as $name) {
            $this->assertFalse(Options::get($name), $name . ' should not have been written.');
        }
        $this->assertFalse(Options::isAllSet());
    }

    public function test_activation_keeps_the_settings_of_a_site_that_is_already_all_set(): void
    {
        Options::reset();
        Options::setAllSet(true);

        Activation::run();

        foreach (self::SETTINGS as $name) {
            $this->assertFalse(Options::get($name), $name . ' should not have been written.');
        }
    }
}
