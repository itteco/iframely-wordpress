<?php

namespace Iframely;

use Iframely\UI\Links;
use Iframely\UI\Notice;
use Iframely\UI\Settings;

class Plugin
{
    public function __construct(string $file)
    {
        register_activation_hook($file, [$this, 'activation']);
        add_action('init', [$this, 'init']);
        $this->run();
    }

    public function init(): void
    {
        load_plugin_textdomain('iframely', false, plugin_basename(IFRAMELY_PLUGIN_DIR) . '/lang/');
        add_filter('network_admin_plugin_action_links_' . IFRAMELY_PLUGIN_FILE, [self::class, 'addSettingsLink']);
        add_filter('plugin_action_links_' . IFRAMELY_PLUGIN_FILE, [self::class, 'addSettingsLink']);
    }

    public function run(): void
    {
        Upgrade::run();
        Notice::run();
        Settings::run();
        if (self::isActivated()) {
            Embed::run();
        }
    }

    public function activation(): void
    {
        Activation::run();
    }

    public static function isActivated(): bool
    {
        return !empty(Options::getApiKey());
    }

    public static function addSettingsLink(array $actions): array
    {
        $settings = sprintf('<a href="%s">%s</a>', Links::settings(), __('Settings', 'iframely'));
        $actions[] = $settings;
        return $actions;
    }

    public static function asset(string $path): string
    {
        return IFRAMELY_PLUGIN_URL . 'build/' . $path;
    }

    public static function view($name, $data = []): void
    {
        foreach ($data as $key => $val) {
            $$key = $val;
        }
        $file = IFRAMELY_PLUGIN_DIR . 'views/' . $name . '.php';
        include($file);
    }

    public static function notice(string $message = '', string $status = 'info', bool $dismissible = true): void
    {
        self::view('partials/notice', [
            'status' => $status,
            'message' => $message,
            'dismissible' => $dismissible,
        ]);
    }
}
