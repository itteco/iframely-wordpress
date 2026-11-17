<?php

defined('ABSPATH') || exit;

use Iframely\UI\Links;

?>

<form method="post" action="" novalidate="novalidate" class="iframely-mt-1em">
  <?php wp_nonce_field('iframely_nonce', 'iframely_nonce'); ?>

  <h2 class="iframely-mt-15em"><?php esc_html_e('Iframely cloud', 'iframely'); ?></h2>
  <p>
    <?php esc_html_e('Iframely service adds and maintains rich media embeds from 1900 providers, and URL preview cards for the rest of the Internet.', 'iframely'); ?>
  </p>
  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <label for="iframely_api_key"><?php esc_html_e('Iframely API key', 'iframely'); ?></label>
      </th>
      <td>
        <input type="text" name="iframely_api_key" id="iframely_api_key" class="regular-text" disabled value="<?php echo esc_attr($api_key); ?>">
        <a href="<?php echo esc_url(Links::tab('', 'reactivate')); ?>" class="button button-secondary"><?php esc_html_e('Change API key', 'iframely'); ?></a>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php esc_html_e('Existing media providers', 'iframely'); ?></h2>
  <p>
    <?php
      /* translators: %s: address of the documentation on embed options */
      echo wp_kses_post(sprintf(__('Your WordPress comes with the number of built-in rich media providers, and Iframely can extend those by providing <a href="%s" target="_blank">per-URL options</a> and other improvements.', 'iframely'), esc_url(Links::link('/docs/options'))));
    ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php esc_html_e('Include built-in providers', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_builtins_replace">
          <input type="checkbox" name="iframely_builtins_replace" id="iframely_builtins_replace" <?php checked($builtins_replace); ?>>
          <?php esc_html_e('Enable Iframely for WordPress providers like YouTube, Twitter, Vimeo, etc.', 'iframely'); ?>
        </label>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php esc_html_e('Your own posts', 'iframely'); ?></h2>
  <p>
    <?php
      /* translators: %s: address of the WordPress article on post embeds */
      echo wp_kses_post(sprintf(__('When you paste this site\'s URL in a post, WordPress shows <a href="%s" target="_blank">its summary</a>.', 'iframely'), esc_url('https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/')));
    ?>
    <br>
    <?php
      /* translators: %s: address of the documentation on cards */
      echo wp_kses_post(sprintf(__('Override it with Iframely <a href="%s" target="_blank">customizable cards</a> instead.', 'iframely'), esc_url(Links::link('/docs/cards'))));
    ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php esc_html_e('Enhance site previews', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_previews_enhance">
          <input type="checkbox" name="iframely_previews_enhance" id="iframely_previews_enhance" <?php checked($previews_enhance); ?>>
          <?php esc_html_e('Show Iframely cards when you link to your own posts', 'iframely'); ?>
        </label>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php esc_html_e('Evergreen cache', 'iframely'); ?></h2>
  <p>
    <?php esc_html_e('WordPress resolves embed codes as you write a post and keeps them cached. Media is refreshed only when you edit & save the post manually.', 'iframely') ?>
    <br>
    <?php esc_html_e('To avoid broken embedded blocks, we recommend automating the cache refreshes.', 'iframely') ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php esc_html_e('Automate caching', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_cache_refresh">
          <input type="checkbox" name="iframely_cache_refresh" id="iframely_cache_refresh" <?php checked($cache_refresh); ?>>
          <?php esc_html_e('Refresh embedded blocks at regular intervals', 'iframely'); ?>
        </label>
      </td>
    </tr>
    <tr>
      <th scope="row">
        <label for="iframely_cache_ttl">
          <?php esc_html_e('Keep cache for', 'iframely'); ?>
        </label>
      </th>
      <td>
        <select name="iframely_cache_ttl" id="iframely_cache_ttl">
          <?php foreach ($cache_ttl_presets as $iframely_ttl => $iframely_ttl_name) : ?>
            <option value="<?php echo esc_attr($iframely_ttl); ?>" <?php selected($cache_ttl, $iframely_ttl); ?>><?php echo esc_html($iframely_ttl_name); ?></option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php esc_html_e('Advanced options', 'iframely'); ?></h2>
  <p>
    <?php
      /* translators: %s: address of the API settings at iframely.com */
      echo wp_kses_post(sprintf(__('Together with your cloud <a href="%s" target="_blank">API settings</a>, optional query string API parameters help you fine-tune embeds.', 'iframely'), esc_url(Links::link('/settings'))));
    ?>
    <br>
    <?php
      /* translators: %s: address of the documentation on API parameters */
      echo wp_kses_post(sprintf(__('You can override your default Iframely settings for this website with <a href="%s" target="_blank">additional API parameters</a>.', 'iframely'), esc_url(Links::link('/docs/parameters'))));
    ?>
  </p>

  <table class="form-table" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <label for="iframely_api_params"><?php esc_html_e('Query string parameters', 'iframely'); ?></label>
      </th>
      <td>
        <input type="text" name="iframely_api_params" id="iframely_api_params" class="regular-text" value="<?php echo esc_attr($api_params); ?>">
        <p class="description"><?php esc_html_e('Example', 'iframely'); ?>: <code>id=1&amp;consent=1</code></p>
      </td>
    </tr>
    </tbody>
  </table>

  <?php submit_button(); ?>

</form>
