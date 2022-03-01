<?php

namespace Iframely\UI;

use Iframely\Embed\Cache;
use Iframely\Options;
use Iframely\Plugin;
use Iframely\Reactivation;

class Settings
{
    public const SAMPLE_URL = 'https://chrome.google.com/webstore/detail/oajehffbidgccdedglcogjoolbdmpjmm';

    public static function run(): void
    {
        // Register menu
        add_action('network_admin_menu', [self::class, 'register']);
        add_action('admin_menu', [self::class, 'register']);

        // Save settings
        add_action('admin_init', [self::class, 'save']);

        // Load assets
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function register(): void
    {
        if (is_multisite()) {
            add_submenu_page('settings.php', __('Iframely', 'iframely'), __('Iframely', 'iframely'), 'install_plugins', 'iframely', [self::class, 'render']);
        } else {
            add_options_page(__('Iframely', 'iframely'), __('Iframely', 'iframely'), 'manage_options', 'iframely', [self::class, 'render']);
        }
    }

    public static function enqueue(): void
    {
        $screen = get_current_screen();
        $screens = ['post', 'settings_page_iframely-network', 'settings_page_iframely'];
        if (!($screen !== null && in_array($screen->id, $screens))) {
            return;
        }
        wp_enqueue_style('iframely-admin', Plugin::asset('index.css'), [], IFRAMELY_VERSION);
    }

    public static function render(): void
    {
        if (!Plugin::isActivated() || Reactivation::isRequest() || Reactivation::inProgress()) {
            Plugin::view('activation');
            return;
        }
        Plugin::view('settings', self::data());
    }

    public static function save(): void
    {
        if (empty($_POST['iframely_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['iframely_nonce'], 'iframely_nonce')) {
            wp_die('Your nonce could not be verified.');
        }

        if (is_multisite()) {
            if (!current_user_can('install_plugins')) {
                wp_die('You don’t have permission to access this resource.');
            }
        } else if (!current_user_can('manage_options')) {
            wp_die('You don’t have permission to access this resource.');
        }

        if (Reactivation::isRequest()) {
            Reactivation::start();
            self::notify(__('Please enter new API key.', 'iframely'));
            return;
        }

        if (Plugin::isActivated() && !Reactivation::inProgress()) {
            self::update();
            self::notify(__('Settings saved.', 'iframely'));
            return;
        }

        self::activate();
    }

    public static function activate(): void
    {
        $key = strtolower(trim(sanitize_text_field($_POST['iframely_api_key'] ?? '')));

        if (!preg_match('/^[a-z0-9]+$/', $key)) {
            self::notify(sprintf(__('Invalid API key. Please check <a href="%s" target="_blank">your keys</a> and try again.', 'iframely'), Links::link('keys')), 'error');
            return;
        }

        $origin = preg_replace('#^https?://#i', '', get_bloginfo('url'));
        $query = http_build_query([
            'api_key' => $key,
            'url' => self::SAMPLE_URL,
            'origin' => $origin,
        ]);
        $url = IFRAMELY_API_ENDPOINT . '?' . $query;
        $request = wp_remote_get($url);

        if (is_wp_error($request)) {
            self::notify(__('HTTP error', 'iframely'), 'error');
            return;
        }

        $body = wp_remote_retrieve_body($request);
        $data = json_decode($body, true, 512);

        if (!empty($data['error'])) {
            $message = __('Sorry, your API key can\'t be verified.', 'iframely');
            if (!empty($data['status'])) {
                $message = sprintf(__('Sorry, your API key can\'t be verified (error code %s). <a href="%s" target="_blank">Learn more</a>', 'iframely'), $data['status'], Links::link('/docs/result-codes'));
            }
            self::notify($message, 'error');
            return;
        }

        Options::setApiKey($key);

        if (Reactivation::inProgress()) {
            Reactivation::end();
            self::notify(__('API key has been updated.', 'iframely'), 'success');
            return;
        }

        Plugin::view('partials/onboarding');
    }

    public static function update(): void
    {
        Options::setBuiltinsReplace((bool)($_POST['iframely_builtins_replace'] ?? false));
        Options::setPreviewsEnhance((bool)($_POST['iframely_previews_enhance'] ?? false));
        Options::setCacheRefresh((bool)($_POST['iframely_cache_refresh'] ?? false));
        Options::setCacheTtl((int)($_POST['iframely_cache_ttl'] ?? 0));
        Options::setApiParams(sanitize_text_field(stripcslashes($_POST['iframely_api_params'] ?? '')));
    }

    public static function notify(string $message = '', $status = 'success'): void
    {
        $action = is_multisite() ? 'network_admin_notices' : 'admin_notices';
        add_action($action, function () use ($message, $status) {
            Plugin::notice($message, $status);
        });
    }

    public static function data(): array
    {
        $data = Options::collect();
        $data['cache_ttl_presets'] = Cache::getTtlPresets();
        $data['tab'] = $_GET['tab'] ?? '';
        $data['action'] = $_GET['action'] ?? '';
        return $data;
    }
}
