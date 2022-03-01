<?php

namespace Iframely\UI;

use Iframely\Plugin;

class Notice
{
    public static function run(): void
    {
        if (is_multisite()) {
            add_action('network_admin_notices', [static::class, 'render']);
        } else {
            add_action('admin_notices', [static::class, 'render']);
        }
    }

    public static function render(): void
    {
        $active = Plugin::isActivated();
        if ($active) {
            return;
        }
        $screen = get_current_screen();
        $screens = ['dashboard', 'plugins'];
        if (is_multisite()) {
            $screens = ['dashboard-network', 'plugins-network'];
        }
        if ($screen !== null && in_array($screen->id, $screens)) {
            Plugin::notice(sprintf(__('Complete Iframely installation. <a href="%s">Enter your API key</a>.', 'iframely'), Links::activation()));
        }
    }
}
