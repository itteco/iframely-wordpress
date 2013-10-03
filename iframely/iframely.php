<?php
/*
Plugin Name: Iframely
Plugin URI: http://wordpress.org/plugins/iframely/
Description: Iframely for WordPress. Embed anything, with responsive widgets.
Author: Itteco Corp.
Version: 0.1.0
Author URI: http://iframely.com/?from=wp
*/

# Define iframely plugin path
if (!defined('IFRAMELY_URL')) {
  define('IFRAMELY_URL', WP_PLUGIN_URL.'/iframely');
}

# Disable all active oembed providers
add_filter('oembed_providers', create_function('', 'return array();'));

# Add iframely as oembed provider for ANY link on line
wp_oembed_add_provider('#(?:^|\r|\n)\s*(https?://[^\s]+)(?:$|\r|\n)#i', 'http://iframely.com/oembed', true);

# Enqueue js for front-end so that some widgets will have proper height
function registering_iframely_js() {
	wp_register_script('iframely_js', IFRAMELY_URL . '/js/iframely.js', array('jquery'), '0.5.2');
	wp_enqueue_script('iframely_js');
}

add_action('wp_enqueue_scripts', 'registering_iframely_js');
