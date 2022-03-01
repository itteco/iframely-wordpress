<?php
/**
 * Plugin Name: Iframely
 * Plugin URI: https://iframely.com/wordpress
 * Description: WP media embeds, cards and blocks.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Iframely.com
 * Author URI: https://iframely.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iframely
 */

namespace Iframely;

if (!defined('ABSPATH')) {
    exit;
}

define('IFRAMELY_VERSION', '1.0.0');
define('IFRAMELY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IFRAMELY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IFRAMELY_PLUGIN_FILE', plugin_basename(__FILE__));
define('IFRAMELY_API_ENDPOINT', 'https://iframe.ly/api/oembed');

spl_autoload_register(static function ($class) {
    if (strpos($class, __NAMESPACE__) !== 0) {
        return false;
    }
    $path = str_replace(__NAMESPACE__, 'app', $class) . '.php';
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
    $file = __DIR__ . DIRECTORY_SEPARATOR . $path;

    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
});

new Plugin(__FILE__);
