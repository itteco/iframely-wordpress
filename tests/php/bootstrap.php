<?php

/**
 * PHPUnit bootstrap: loads the WordPress test suite, then the plugin.
 *
 * Inside wp-env the WordPress test library lives at $WP_TESTS_DIR
 * (/wordpress-phpunit). Run through `npm run test:php`.
 */

$iframely_plugin_dir = dirname(__DIR__, 2);
$iframely_tests_dir = getenv('WP_TESTS_DIR');

if (!$iframely_tests_dir) {
    $iframely_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($iframely_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "WordPress test library not found in {$iframely_tests_dir}. Run the tests with `npm run test:php`.\n");
    exit(1);
}

if (!file_exists($iframely_plugin_dir . '/vendor/autoload.php')) {
    fwrite(STDERR, "Composer dependencies are missing. Run `npm run composer install` first.\n");
    exit(1);
}

// wp-env derives wp-tests-config.php from the site's wp-config.php, so by default the
// test suite would drop and reinstall the tables of the running site (port 8889) on
// every run. A separate table prefix keeps PHPUnit and the browsable site apart.
// The variable is read by wp-config.php through getenv_docker() and is inherited by
// the test suite's install subprocess.
putenv('WORDPRESS_TABLE_PREFIX=wptests_');

define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $iframely_plugin_dir . '/vendor/yoast/phpunit-polyfills');

require_once $iframely_tests_dir . '/includes/functions.php';

// The plugin only registers its embed features when an API key is stored (see
// Plugin::run()). Store one before loading the plugin, so the tests start from the
// state of a configured site. No request ever leaves the test suite: see TestCase.
define('IFRAMELY_TESTS_API_KEY', 'phpunitkey0123456789abcdef');

tests_add_filter('muplugins_loaded', static function () use ($iframely_plugin_dir) {
    if (is_multisite()) {
        update_site_option('iframely_api_key', IFRAMELY_TESTS_API_KEY);
    } else {
        update_option('iframely_api_key', IFRAMELY_TESTS_API_KEY);
    }

    require $iframely_plugin_dir . '/iframely.php';
});

require $iframely_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/TestCase.php';
