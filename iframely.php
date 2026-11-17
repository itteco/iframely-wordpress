<?php

/**
 * Plugin Name: Iframely
 * Plugin URI: https://iframely.com/wordpress
 * Description: WP media embeds, cards and blocks.
 * Version: 1.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Iframely.com
 * Author URI: https://iframely.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iframely
 * Domain Path: /lang
 */

namespace Iframely;

if (!defined('ABSPATH')) {
    exit;
}

define('IFRAMELY_VERSION', '1.2.0');
define('IFRAMELY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IFRAMELY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IFRAMELY_PLUGIN_FILE', plugin_basename(__FILE__));
define('IFRAMELY_API_ENDPOINT', 'https://iframe.ly/api/oembed');

spl_autoload_register(static function ($class): void {
    if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
        return;
    }
    $path = 'app' . substr($class, strlen(__NAMESPACE__)) . '.php';
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $path;

    if (file_exists($file)) {
        require_once $file;
    }
});

new Plugin(__FILE__);
