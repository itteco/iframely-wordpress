<?php

/**
 * Runs when the plugin is deleted on the Plugins screen.
 *
 * It removes the settings of the plugin, the API key among them. Posts are left as
 * they are: their URLs keep working with the embeds of WordPress itself.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// The plugin is not loaded at this point, and neither is its autoloader.
require_once __DIR__ . '/app/Options.php';

Iframely\Options::reset();
