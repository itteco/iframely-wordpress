<?php

namespace Iframely\Tests;

use Iframely\UI\Links;
use Iframely\UI\Settings;

class LinksTest extends TestCase
{
    public function test_links_to_iframely_com_carry_the_campaign_source(): void
    {
        $this->assertSame('https://iframely.com/?utm_source=wordpress-plugin', Links::link());
        $this->assertSame('https://iframely.com/keys?utm_source=wordpress-plugin', Links::link('keys'));
        $this->assertSame('https://iframely.com/docs/result-codes?utm_source=wordpress-plugin', Links::link('/docs/result-codes'));
    }

    public function test_tab_links_extend_the_settings_page_url(): void
    {
        $userId = self::factory()->user->create(['role' => 'administrator']);
        if (is_multisite()) {
            grant_super_admin($userId);
        }
        wp_set_current_user($userId);
        Settings::register();

        $settings = Links::settings();

        $this->assertStringContainsString('page=iframely', $settings);
        $this->assertSame($settings, Links::activation());
        $this->assertSame($settings, Links::tab());
        $this->assertSame($settings . '&tab=customize', Links::tab('customize'));
        $this->assertSame($settings . '&action=reactivate', Links::tab('', 'reactivate'));
        $this->assertSame($settings . '&tab=help&action=reactivate', Links::tab('help', 'reactivate'));
    }
}
