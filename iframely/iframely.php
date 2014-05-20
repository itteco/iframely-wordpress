<?php
/*
Plugin Name: Iframely
Plugin URI: http://wordpress.org/plugins/iframely/
Description: Iframely for WordPress. Embed anything, with responsive widgets.
Author: Itteco Corp.
Version: 0.2.2
Author URI: http://iframe.ly/?from=wp
*/

# Define iframely plugin path
if ( !defined( 'IFRAMELY_URL' ) ) {
  define( 'IFRAMELY_URL', WP_PLUGIN_URL.'/iframely' );
}

# Add iframely as oembed provider for ANY link on line, if we have API key and this option is not disabled
if ( !get_site_option( 'iframely_only_shortcode' ) && get_site_option('iframely_api_key') ) {

    # Disable all active oembed providers
    add_filter( 'oembed_providers', create_function('', 'return array();'));

    # Also disable default providers list
    require_once( ABSPATH . WPINC . '/class-oembed.php' );
    $wp_oembed = _wp_oembed_get_object();
    $wp_oembed->providers = array();

    # Add iframely as oembed provider for ANY url, yes it will process any url on separate line with wp oembed functions
    wp_oembed_add_provider( '#https?://[^\s]+#i', iframely_create_api_link(), true );
}

# Add iframely as oembed provider for any iframe.ly shorten link
wp_oembed_add_provider( '#https?://iframe\.ly/.+#i', iframely_create_api_link(), true );

# Enqueue iframely and jquery js for front-end
function registering_iframely_js() {
	wp_register_script( 'iframely_js', IFRAMELY_URL . '/js/iframely.js', array('jquery'), '0.5.2' );
	wp_enqueue_script( 'iframely_js' );
}

add_action( 'wp_enqueue_scripts', 'registering_iframely_js');

# Register [iframely] shortcode
add_shortcode( 'iframely', 'embed_iframely' );

# Function to process content in iframely shortcode, ex: [iframely]http://anything[/iframely]
function embed_iframely( $atts, $content = '' ) {

    # Read url from 'url' attribute if not empty
    if ( !empty( $atts['url'] ) ) $content = $atts['url'];
    $content = str_replace( '&#038;', '&amp;', trim( $content ) );
    $content = str_replace( '&amp;', '&', $content );

    # Read iframely API key from options
    $api_key = trim( get_site_option( 'iframely_api_key' ) );

    # Print error message if API key is empty and not an iframe.ly shorten url inside shortcode
    if ( empty( $api_key ) && !preg_match( '#https?://iframe\.ly/.+#i', $content ) ) {
        return '[Please, configure your <a href="http://iframe.ly/api">API key</a> in Iframely options or manually shorten URL at <a href="http://iframe.ly">iframe.ly</a>]';
    }

    # Create internal wp oembed class to get list of oembed providers
    $wp_oembed = _wp_oembed_get_object();

    # Save current providers
    $old_providers = $wp_oembed->providers;

    # With API key we can use iframely as provider for any url inside our shortcode
    if ( !empty( $api_key ) ) {
        $wp_oembed->providers = array( '#https?://[^\s]+#i' => array( iframely_create_api_link(), true ) );
    }
    # Without API key we can use iframely as provider only for iframe.ly shorten link
    else {
        $wp_oembed->providers = array( '#https?://iframe\.ly/.+#i' => array( iframely_create_api_link(), true ) );
    }

    # Get global WP_Embed class, to use 'shortcode' method from it
    global $wp_embed;

    # Get embed code for the url using internal wp embed object (it cache results for the post automatically)
    $code = $wp_embed->shortcode( $atts, $content );

    # Set list of the original oembed providers back
    $wp_oembed->providers = $old_providers;

    # Return code to embed for the url inside shortcode
    return $code;
}

# Create link to iframely API backend
function iframely_create_api_link () {

    # Read url of the current blog
    $blog_name = preg_replace( '#^https?://#i', '', get_bloginfo( 'url' ) );
    # Read Host Widgets from plugin options
    $host_widgets = get_site_option( 'iframely_host_widgets' );
    # Read API key from plugin options
    $api_key = trim( get_site_option( 'iframely_api_key' ) );

    $link = 'http://iframe.ly/api/oembed?&origin=' . $blog_name;

    # Append API key
    if ( $api_key ) {
        $link .= '&api_key=' . $api_key;
    }

    # Append Host Widgets
    if ( $host_widgets ) {
        $link .= '&iframe=' . 1;
    }

    return $link;
}

# Create iframely settings menu for admin
add_action( 'admin_menu', 'iframely_create_menu' );
add_action( 'network_admin_menu', 'iframely_network_admin_create_menu' );

# Create link to plugin options page from plugins list
function iframely_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=iframely/iframely.php">Settings</a>';
  	array_push( $links, $settings_link );
  	return $links;
}

$iframely_plugin_basename = plugin_basename( __FILE__ );
add_filter( 'plugin_action_links_' . $iframely_plugin_basename, 'iframely_plugin_add_settings_link' );

# Create new top level menu for sites
function iframely_create_menu() {
    add_menu_page('Iframely Options', 'Iframely', 'install_plugins', 'iframely_settings_page', 'iframely_settings_page');
}

# Create new top level menu for network admin
function iframely_network_admin_create_menu() {
    add_menu_page('Iframely Options', 'Iframely', 'manage_options', 'iframely_settings_page', 'iframely_settings_page');
}


function iframely_update_option($name, $value) {
    return is_multisite() ? update_site_option($name, $value) : update_option($name, $value);
}

function iframely_settings_page() {

?>

    <style type="text/css">
        .iframely_options_page ul {
            list-style: disc;
            padding-left: 40px;
        }
    </style>

<div class="wrap iframely_options_page">

<h1>How to use Iframely</h1>

<p>Iframely will take URL in your post and replace it with (responsive, if possible) embed code. We cover well over 1000 domains. <a href="http://iframe.ly/domains" target="_blank">See examples</a>.</p>

<ul>
<li><p><strong>URL on a separate line</strong>: Shorten your URL first at <a href="http://iframe.ly?from=wp" target="_blank">iframe.ly</a> <br>and paste short URL on a separate line in your post</p></li>
<li><p><strong>With shortcode</strong>: <code>[iframely]http://iframe.ly/bFkV[/iframely]</code> <br>or <code>[iframely url=http://iframe.ly/bFkV/]</code></p></li>
<li><p><strong>With API Key - any URL</strong>: URL on a separate line and also with shortcode, but with any URL <br>and with no need to shorten URLs manually at iframe.ly first. <br>This option requires (FREE) <a href="http://iframe.ly/api" target="_blank"</a><strong>API KEY</strong></a> </p></li>
</ul>


<p><em>Note</em>: Some people expect Iframely to wrap URLs with <code>&lt;iframe src=...&gt;</code> code. That's not what Iframely is for. It converts original URLs into native embed codes itself.</p>
<p></br></p>

<h1>Configure Your Options</h1>

<form method="post" action="">
    <?php

        if (isset($_POST['_wpnonce']) && isset($_POST['submit'])) {

            iframely_update_option('iframely_api_key', trim($_POST['iframely_api_key']));
            iframely_update_option('iframely_only_shortcode', (int)$_POST['iframely_only_shortcode']);
            iframely_update_option('iframely_host_widgets', (int)$_POST['iframely_host_widgets']);
        }

        wp_nonce_field('form-settings');
    ?>

    <ul>
        <li>
            <p>Your Iframely API Key: </p>
            <p><input type="text" style="width: 250px;" name="iframely_api_key" value="<?php echo get_site_option('iframely_api_key'); ?>" /></p>
            <p> It activates all URLs both in shortcode and when used on a separate line. When left empty, <a href="http://iframe.ly?from=wp">Shorten URL</a> manually first.</br>
            Get your <a href="http://iframe.ly/api" target="_blank">FREE API key here</a>.</p>
        </li>

        <li>
            <p><input type="checkbox" name="iframely_only_shortcode" value="1" <?php if (get_site_option('iframely_only_shortcode')) { ?> checked="checked" <?php } ?> /> Only use Iframely with <code>[iframely]</code> shortcode</p>
            <p>It will block Iframely from intercepting all URLs in your editor that may be covered by other embeds plugins you have installed, e.g. a Jetpack.</p>
        </li>
        
        <li>
            <p><input type="checkbox" name="iframely_host_widgets" value="1" <?php if (get_site_option('iframely_host_widgets')) { ?> checked="checked" <?php } ?> /> Host and Proxy Embed Widgets</p>
            <p>This <em>isn't implemented yet</em>. But put a check to let us know you would be interested in this feature.</br>
            For performance/load times, SSL or even autoplay videos, we could wrap native embed codes and proxy widget views through our servers.</p>
        </li>
        
    </ul>
    
    <?php submit_button(); ?>
    
</form>
<script type="text/javascript">
    jQuery( '.iframely_options_page form' ).submit( function() {
        var $api_key_input = jQuery(this).find('[name="iframely_api_key"]');

        if (!$api_key_input.val().length) return true;

        var origin = "<?php print( preg_replace( '#^https?://#i', '', get_bloginfo( 'url' ) ) )?>";

        // CHECK HTTPS
        var url = location.protocol + "//iframe.ly/api/oembed?api_key=" + $api_key_input.val() + "&url=https://chrome.google.com/webstore/detail/oajehffbidgccdedglcogjoolbdmpjmm&origin=" + origin;
        var api_key_check = true;
        jQuery.ajax({
            url: url,
            error: function() {
                $api_key_input_container = $api_key_input.parent();
                $api_key_input_container.find('.iframely_options_page_error').remove();
                $api_key_input_container.prepend(jQuery('<div style="color: red" class="iframely_options_page_error">Oops, invalid API Key provided.</div>').fadeIn());
                api_key_check = false;
            },
            async: false
        });

        if (!api_key_check) return false;
    });
</script>
</div>
<?php } ?>