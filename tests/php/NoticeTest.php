<?php

namespace Iframely\Tests;

use Iframely\Options;
use Iframely\UI\Notice;

/**
 * The "enter your API key" reminder in the admin.
 */
class NoticeTest extends TestCase
{
    public function tear_down(): void
    {
        set_current_screen('front');

        parent::tear_down();
    }

    private function renderOn(string $screen): string
    {
        set_current_screen($screen);

        ob_start();
        Notice::render();
        return (string)ob_get_clean();
    }

    /**
     * @return string[]
     */
    private static function reminderScreens(): array
    {
        return is_multisite() ? ['dashboard-network', 'plugins-network'] : ['dashboard', 'plugins'];
    }

    public function test_reminder_is_hooked_to_the_right_admin(): void
    {
        $hook = is_multisite() ? 'network_admin_notices' : 'admin_notices';
        $other = is_multisite() ? 'admin_notices' : 'network_admin_notices';

        $this->assertSame(10, has_action($hook, [Notice::class, 'render']));
        $this->assertFalse(has_action($other, [Notice::class, 'render']));
    }

    public function test_reminder_is_shown_on_dashboard_and_plugins_screens_without_an_api_key(): void
    {
        Options::delete('iframely_api_key');

        foreach (self::reminderScreens() as $screen) {
            $html = $this->renderOn($screen);

            $this->assertStringContainsString('Complete Iframely installation.', $html, $screen);
            $this->assertStringContainsString('notice-info', $html, $screen);
        }
    }

    public function test_reminder_is_not_shown_on_other_screens(): void
    {
        Options::delete('iframely_api_key');

        $this->assertSame('', $this->renderOn('edit-post'));
        $this->assertSame('', $this->renderOn('options-general'));
    }

    public function test_reminder_is_not_shown_once_an_api_key_is_stored(): void
    {
        foreach (self::reminderScreens() as $screen) {
            $this->assertSame('', $this->renderOn($screen), $screen);
        }
    }
}
