<?php
/*
Plugin Name: Iframely
Plugin URI: http://wordpress.org/plugins/iframely/
Description: Iframely for WordPress. Embed anything, with responsive widgets.
Author: Itteco Corp.
Version: 0.4.0
Author URI: https://iframely.com/?from=wp
*/

# Define iframely plugin path
if ( !defined( 'IFRAMELY_URL' ) ) {
  define( 'IFRAMELY_URL', WP_PLUGIN_URL.'/iframely' );
}

# Always add Iframely as provider. Last to the list. If not 'only_shortcode', then this provider will disable all default ones

# Add iframely as oembed provider for ANY url, yes it will process any url on separate line with wp oembed functions
wp_oembed_add_provider( '#https?://[^\s]+#i', iframely_create_api_link(), true );

# Make the Iframely endpoint to be the first in queue, otherwise default regexp are precedent
add_filter( 'oembed_providers', 'maybe_reverse_oembed_providers');

# Remove short-circuit for self-embeds, that forces it for all the sites and disables our summary cards for own domain
add_filter( 'pre_oembed_result', 'maybe_remove_wp_self_embeds', PHP_INT_MAX, 3 );
# alternatively: remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );


# Always add iframely as oembed provider for any iframe.ly short link
wp_oembed_add_provider( '#https?://iframe\.ly/.+#i', iframely_create_api_link(), true );

function maybe_remove_wp_self_embeds( $result, $url, $args ) { 
        
    return get_site_option( 'publish_iframely_cards') ? null : $result;
}

function maybe_reverse_oembed_providers ($providers) {
    
    # iframely_only_shortcode option is unset in shortcode, so that the filter can work. Then returned back.
    if ( !get_site_option( 'iframely_only_shortcode' ) ) {
        return array_reverse($providers);
    }
    else {
        return $providers;
    }
}

# fix cache ttl
add_filter('oembed_ttl', 'maybe_disable_cache', 99, 4);
function maybe_disable_cache($ttl, $url, $attr, $post_ID) {
        
        $iframely_ttl = DAY_IN_SECONDS * (int)get_site_option('iframely_cache_ttl');

        if ($iframely_ttl > 0) { 

            global $wp_embed;

            # Copy keys from wp-embed        
            $key_suffix = md5( $url . serialize( $attr ) );
            $cachekey = '_oembed_' . $key_suffix;
            $cachekey_time = '_oembed_time_' . $key_suffix;

            $cache_time = get_post_meta( $post_ID, $cachekey_time, true );

            # Disable cache only once - for first page view
            # After that, the $cache_time is cleared so that Iframely plugin can tell it already worked for that URL
            # But other parallel pageviews will still use cache
            if ($cache_time && (time() - $cache_time  > $iframely_ttl) ) {
                $wp_embed->usecache = false;
                delete_post_meta( $post_ID, $cachekey_time);
                return $iframely_ttl;
            } else {
                return $ttl;
            }
        } else {
            return $ttl;
        } 
        
    }

# Register [iframely] shortcode
add_shortcode( 'iframely', 'embed_iframely' );

# rewrite oembed discovery
add_filter( 'oembed_endpoint_url', 'publish_embeds_via_iframely', 10, 2) ;

function publish_embeds_via_iframely($url, $permalink, $format = 'json') {

    if ('' !== $permalink  && get_site_option( 'publish_iframely_cards')) {

        $endpoint = iframely_create_api_link ('discovery');        

        $endpoint = add_query_arg( array(
            'url'    => urlencode( $permalink ),
            'format' => strpos($url, 'format=xml') ? 'xml': 'json',
            // $format isn't passed inside the filter for some reason, hence the workaround
        ), $endpoint );

        return $endpoint;

    } else {
        return $url;
    }
    
}

# Function to process content in iframely shortcode, ex: [iframely]http://anything[/iframely]
function embed_iframely( $atts, $content = '' ) {

    # Read url from 'url' attribute if not empty
    if ( !empty( $atts['url'] ) ) $content = $atts['url'];
    $content = str_replace( '&#038;', '&amp;', trim( $content ) );
    $content = str_replace( '&amp;', '&', $content );

    # Get global WP_Embed class, to use 'shortcode' method from it
    global $wp_embed;

    # With API key we can use iframely as provider for any url inside our shortcode
    # Add handler, if it wasn't added before
    $only_shortcode_before = get_site_option( 'iframely_only_shortcode' );

    if ($only_shortcode_before) {
        iframely_update_option('iframely_only_shortcode', null);
    }

    # Get embed code for the url using internal wp embed object (it caches results for the post automatically)
    $code = $wp_embed->shortcode( $atts, $content );

    # return only_shortcode option to what it was before
    if ($only_shortcode_before) {
        iframely_update_option('iframely_only_shortcode', $only_shortcode_before);
    }

    # Return code to embed for the url inside shortcode
    return $code;
}

# Create link to iframely API backend
function iframely_create_api_link ($origin = '') {

    # Read API key from plugin options
    $api_key = trim( get_site_option( 'iframely_api_key' ) );
    $link = $api_key ? 'http://iframe.ly/api/oembed': 'http://open.iframe.ly/api/oembed';

    $link = add_query_arg( array(
        'origin'    => '' !== $origin ? $origin : preg_replace( '#^https?://#i', '', get_bloginfo( 'url' ) ),
        'api_key' => $api_key ? $api_key : false
    ), $link );

    $api_params = trim( get_site_option( 'iframely_api_params' ) );

    if ( !empty( $api_params )) {
        parse_str( $api_params, $params );
        $link = add_query_arg( $params, $link );
    }
    
    return $link;
}


# Create iframely settings menu for admin
add_action( 'admin_menu', 'iframely_create_menu' );
add_action( 'network_admin_menu', 'iframely_network_admin_create_menu' );

# Create link to plugin options page from plugins list
function iframely_plugin_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=iframely_settings_page">Settings</a>';
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

<p>Iframely will take URL in your post and replace it with (responsive, if possible) embed code. We cover well over 1700 domains. Plus summary cards for other URLs. You can <a href="https://iframely.com/embed" target="_blank">test your URLs here</a>.</p>

<ul>
<li><p>Put URL on a separate line - standard WP way</p></li>
<li><p>Or use shortcode <code>[iframely]http://...[/iframely]</code></li>
</ul>


<p><em>Note</em>: Some people expect Iframely to wrap URLs with <code>&lt;iframe src=...&gt;</code> code. That's not what Iframely is for. It converts original URLs into native embed codes itself.</p>

<form method="post" action="">

<h1>Configure your options</h1>

    <?php

        if (isset($_POST['_wpnonce']) && isset($_POST['submit'])) {

            iframely_update_option('iframely_api_key', trim($_POST['iframely_api_key']));
            iframely_update_option('iframely_only_shortcode', (isset($_POST['iframely_only_shortcode'])) ? (int)$_POST['iframely_only_shortcode'] : null);
            iframely_update_option('publish_iframely_cards', (isset($_POST['publish_iframely_cards'])) ? (int)$_POST['publish_iframely_cards'] : null);
            iframely_update_option('iframely_api_params', trim($_POST['iframely_api_params']));
            iframely_update_option('iframely_cache_ttl', (isset($_POST['iframely_cache_ttl'])) ? (int)trim($_POST['iframely_cache_ttl']) : 0);
        }

        wp_nonce_field('form-settings');
    ?>

    <ul>
        <li>
            <p>Your Iframely API Key (get one at <a href="https://iframely.com/?from=wp" target="_blank">iframely.com</a>): </p>
            <p><input type="text" style="width: 250px;" name="iframely_api_key" value="<?php echo get_site_option('iframely_api_key'); ?>" placeholder="required"/></p>
        </li>

        <li>
            <p>Optional API query-string params (<a href="https://iframely.com/docs/parameters" target="_blank">see this doc</a>): </p>
            <p><input type="text" style="width: 250px;" name="iframely_api_params" value="<?php echo get_site_option('iframely_api_params'); ?>" placeholder="Format as &align=left&omit_css=1" /></p>
        </li>

        <li>
            <p>Cache the embed codes for this number of days: </p>
            <p><input type="text" style="width: 250px;" name="iframely_cache_ttl" 
                value="<?php echo ((null !== get_site_option('iframely_cache_ttl')) ? get_site_option('iframely_cache_ttl') : ''); ?>" placeholder="Number of days, 1 - recommended" /></p>
            <p>By default, WordPress will refresh embed codes <a href="https://core.trac.wordpress.org/ticket/37597" target="_blank">only</a> when you edit and save a post.<br>
            This isn't right. Embed codes should be refreshed periodically.<br>
            Configure how often it will be done. As in "Once every XX days for each post".<br>
            Enter 0 to skip Iframely's cache handler and use WP defaults.
            </p>                
        </li>        

        <li>
            <p><input type="checkbox" name="iframely_only_shortcode" value="1" <?php if (get_site_option('iframely_only_shortcode')) { ?> checked="checked" <?php } ?> /> Do not override default embed providers</p>
            <p>It will block Iframely from intercepting all URLs in your editor that may be covered by other embeds plugins you have installed, e.g. a Jetpack or default embeds supported by WordPress.<br>
            Although, we should support the same providers and output the same code, just make it responsive.<br>
            Iframely shortcode will still process such URLs regardless of this setting.
        </p>
        </li>

        <li>
            <p><input type="checkbox" name="publish_iframely_cards" value="1" <?php if (get_site_option('publish_iframely_cards')) { ?> checked="checked" <?php } ?> /> Use Iframely <a href="https://iframely.com/docs/cards" target="_blanak">summary cards</a> as embeds for your site</p>
            <p>Since WP 4.4 your site <a href="https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/" target="_blank">publishes embeds</a> by default so that <strong>your own</strong> and other WP sites can embed summaries of your posts.                 
            <br>Use this option to override the default widgets and use nice Iframely cards instead. 
            <br>Customize design of your cards <a href="https://iframely.com/customize" target="_blank">here</a>.
            <br>Preview your Iframely cards <a href="https://iframely.com/embed" target="_blank">here</a>.
        </p>
        </li>

    </ul>

    
    <?php submit_button(); ?>
    
</form>
<script type="text/javascript">
    jQuery( '.iframely_options_page form' ).submit( function() {
        var $api_key_input = jQuery(this).find('[name="iframely_api_key"]');
        var $enable_cards = jQuery(this).find('[name="publish_iframely_cards"]');
                  
        function showError (msg) {

            $api_key_input_container = $api_key_input.parent();
            $api_key_input_container.find('.iframely_options_page_error').remove();
            $api_key_input_container.prepend(
                jQuery('<div style="color: red" class="iframely_options_page_error">' + msg + '</div>').fadeIn());
        }

        if (!$api_key_input.val().length) {
             showError('Sorry, API key is required. Get yours at https://iframely.com');
             return false;
        }


        var origin = "<?php print( preg_replace( '#^https?://#i', '', get_bloginfo( 'url' ) ) )?>";

        // CHECK HTTPS
        var url = location.protocol + "//iframe.ly/api/oembed?api_key=" + $api_key_input.val() + "&url=https://chrome.google.com/webstore/detail/oajehffbidgccdedglcogjoolbdmpjmm&origin=" + origin;
        var api_key_check = true;
        jQuery.ajax({
            url: url,
            error: function() {
                showError('Oops, API Key can not be verified. Test URL/API call didn\'t pass. <br>Make sure your <a href="https://iframely.com/settings" target="_blank">API settings</a> do not "Respond with error 417 when API call results in no embed codes"');
                api_key_check = false;
            },
            async: false
        });

        if (!api_key_check) {return false};
    });
</script>
</div>
<?php } ?>