<?php
/*
Plugin Name: Iframely
Plugin URI: http://wordpress.org/plugins/iframely/
Description: Iframely for WordPress. Embed anything, with responsive widgets.
Author: Itteco Corp.
Version: 0.2.0
Author URI: http://iframely.com/?from=wp
*/

# Define iframely plugin path
if ( !defined( 'IFRAMELY_URL' ) ) {
  define( 'IFRAMELY_URL', WP_PLUGIN_URL.'/iframely' );
}

# Add iframely as oembed provider for ANY link on line, if we have API key and this option is not disabled
if ( !get_option( 'iframely_only_shortcode' ) && get_option('iframely_api_key') ) {

    # Disable all active oembed providers
    add_filter( 'oembed_providers', create_function('', 'return array();'));

    # Also disable default providers list
    require_once( ABSPATH . WPINC . '/class-oembed.php' );
    $wp_oembed = _wp_oembed_get_object();
    $wp_oembed->providers = array();

    # Add iframely as oembed provider for ANY url, yes it will process any url on separate line with wp oembed functions
    $api_key = get_option('iframely_api_key');
    wp_oembed_add_provider( '#https?://[^\s]+#i', iframely_create_api_link( $api_key ), true );
}

# Add iframely as oembed provider for any iframe.ly shorten link
wp_oembed_add_provider( 'http://iframe.ly/*', 'http://iframe.ly/api/oembed', false );

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
    $content = trim($content);

    # Read iframely API key from options
    $api_key = get_option('iframely_api_key');

    # Print error message if API key is empty and not an iframe.ly shorten url inside shortcode
    if ( empty( $api_key ) && strpos( $content, 'http://iframe.ly' ) !== 0 ) {
        return '[Please, enter your <a href="http://iframe.ly/api">API key</a> in Iframely options or manually shorten URL at <a href="http://iframe.ly">iframe.ly</a>]';
    }

    # Create internal wp oembed class to get list of oembed providers
    $wp_oembed = _wp_oembed_get_object();

    # Save current providers
    $old_providers = $wp_oembed->providers;

    # With API key we can use iframely as provider for any url inside our shortcode
    if ( !empty( $api_key ) ) {
        $wp_oembed->providers = array( '#https?://[^\s]+#i' => array( iframely_create_api_link( $api_key ), true ) );
    }
    # Without API key we can use iframely as provider only for iframe.ly shorten link
    else {
        $wp_oembed->providers = array( 'http://iframe.ly/*' => array( 'http://iframe.ly/api/oembed', false ) );
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

function iframely_create_api_link ( $api_key ) {
    $blog_name = get_bloginfo('url');
    return "http://iframe.ly/api/oembed?api_key={$api_key}&origin={$blog_name}";
}

# Create iframely settings menu
add_action('admin_menu', 'iframely_create_menu');

function iframely_create_menu() {

	# Create new top-level menu
	add_menu_page('Iframely Options', 'Iframely Options', 'administrator', __FILE__, 'iframely_settings_page');

	# Call register settings function
	add_action( 'admin_init', 'register_iframely_settings' );
}

function register_iframely_settings() {
	register_setting( 'iframely-settings-group', 'iframely_api_key' );
    register_setting( 'iframely-settings-group', 'iframely_only_shortcode' );
}

function iframely_settings_page() {
?>
<div class="wrap">

<h1>Iframely Options</h1>
<p>Iframely will take URLs you place in editor on a separate line and will try to detect responsive embed codes for it. If successful, Iframely will put embed HTML code in your post. You can also use Iframely with <code>[iframely]http://url.com[/iframely]</code> shortcode.</p>

<form method="post" action="options.php">
    <?php settings_fields( 'iframely-settings-group' ); ?>
    <?php do_settings_sections( 'iframely-settings-group' ); ?>

    <ul>
        <li>
            <p>Enter Iframely API Key: <input type="text" name="iframely_api_key" value="<?php echo get_option('iframely_api_key'); ?>" /></p>

            <ul>
                <li>If absent, Iframely will only work with URLs of iframe.ly domain. It mains you will have to manually <a href="http://iframe.ly" data-hasqtip="9" aria-describedby="qtip-9">shorten URL</a> first</li>
                <li><a href="http://iframe.ly/api">Get a FREE API key here</a></li>
            </ul>
        </li>

        <li>
            <p><input type="checkbox" name="iframely_only_shortcode" value="1" <?php if (get_option('iframely_only_shortcode')) { ?> checked="checked" <?php } ?> /> Only use Iframely with [iframely] shortcode</p>

            <ul>
                <li>It will block Iframely from intercepting all URLs in your editor that may be covered by embeds plugins you have installed, e.g. a Jetpack.</li>
            </ul>
            <?php submit_button(); ?>
        </li>
    </ul>
</form>
</div>
<?php } ?>