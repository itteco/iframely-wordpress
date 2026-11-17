<?php

namespace Iframely\Tests;

use Iframely\Plugin;
use WP_UnitTestCase;

/**
 * Proves the test harness works: WordPress is booted and the plugin is loaded.
 */
class SmokeTest extends WP_UnitTestCase
{
    public function test_plugin_is_loaded(): void
    {
        $this->assertTrue(defined('IFRAMELY_VERSION'));
        $this->assertTrue(class_exists(Plugin::class));
        $this->assertSame('iframely/iframely.php', IFRAMELY_PLUGIN_FILE);
    }

    public function test_plugin_version_matches_header(): void
    {
        $header = get_file_data(IFRAMELY_PLUGIN_DIR . 'iframely.php', ['Version' => 'Version']);
        $this->assertSame(IFRAMELY_VERSION, $header['Version']);
    }

    public function test_plugin_is_not_activated_without_api_key(): void
    {
        $this->assertFalse(Plugin::isActivated());
    }
}
