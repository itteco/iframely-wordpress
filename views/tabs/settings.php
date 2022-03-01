<?php use Iframely\UI\Links; ?>

<form method="post" action="" novalidate="novalidate" class="iframely-mt-1em">
  <?php wp_nonce_field('iframely_nonce', 'iframely_nonce'); ?>

  <h2 class="iframely-mt-15em"><?php _e('Iframely cloud', 'iframely'); ?></h2>
  <p>
    <?php _e('Iframely service adds and maintains rich media embeds from 1900 providers, and URL preview cards for the rest of the Internet.', 'iframely'); ?>
  </p>
  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <label for="iframely_api_key"><?php _e('Iframely API key', 'iframely'); ?></label>
      </th>
      <td>
        <input type="text" name="iframely_api_key" id="iframely_api_key" class="regular-text" disabled value="<?php esc_attr_e($api_key); ?>">
        <a href="<?php echo Links::tab('', 'reactivate'); ?>" class="button button-secondary"><?php _e('Change API key', 'iframely'); ?></a>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php _e('Existing media providers', 'iframely'); ?></h2>
  <p>
    <?php printf(__('Your WordPress comes with the number of built-in rich media providers, and Iframely can extend those by providing <a href="%s" target="_blank">per-URL options</a> and other improvements.', 'iframely'), Links::link('/docs/options')); ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php _e('Include built-in providers', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_builtins_replace">
          <input type="checkbox" name="iframely_builtins_replace" id="iframely_builtins_replace" <?php checked($builtins_replace); ?>>
          <?php _e('Enable Iframely for WordPress providers like YouTube, Twitter, Vimeo, etc.', 'iframely'); ?>
        </label>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php _e('Your own posts', 'iframely'); ?></h2>
  <p>
    <?php printf(__('When you paste this site\'s URL in a post, WordPress shows <a href="%s" target="_blank">its summary</a>.', 'iframely'), 'https://make.wordpress.org/core/2015/10/28/new-embeds-feature-in-wordpress-4-4/'); ?>
    <br>
    <?php printf(__('Override it with Iframely <a href="%s" target="_blank">customizable cards</a> instead.', 'iframely'), Links::link('/docs/cards')); ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php _e('Enhance site previews', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_previews_enhance">
          <input type="checkbox" name="iframely_previews_enhance" id="iframely_previews_enhance" <?php checked($previews_enhance); ?>>
          <?php _e('Show Iframely cards when you link to your own posts', 'iframely'); ?>
        </label>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php _e('Evergreen cache', 'iframely'); ?></h2>
  <p>
    <?php _e('WordPress resolves embed codes as you write a post and keeps them cached. Media is refreshed only when you edit & save the post manually.', 'iframely') ?>
    <br>
    <?php _e('To avoid broken embedded blocks, we recommend automating the cache refreshes.', 'iframely') ?>
  </p>

  <table class="form-table iframely-mb-2em" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <?php _e('Automate caching', 'iframely'); ?>
      </th>
      <td>
        <label for="iframely_cache_refresh">
          <input type="checkbox" name="iframely_cache_refresh" id="iframely_cache_refresh" <?php checked($cache_refresh); ?>>
          <?php _e('Refresh embedded blocks at regular intervals', 'iframely'); ?>
        </label>
      </td>
    </tr>
    <tr>
      <th scope="row">
        <label for="iframely_cache_ttl">
          <?php _e('Keep cache for', 'iframely'); ?>
        </label>
      </th>
      <td>
        <select name="iframely_cache_ttl" id="iframely_cache_ttl">
          <?php foreach ($cache_ttl_presets as $value => $name): ?>
            <option value="<?php esc_attr_e($value); ?>" <?php selected($cache_ttl, $value); ?>><?php esc_html_e($name); ?></option>
          <?php endforeach; ?>
        </select>
      </td>
    </tr>
    </tbody>
  </table>

  <h2><?php _e('Advanced options', 'iframely'); ?></h2>
  <p>
    <?php printf(__('Together with your cloud <a href="%s" target="_blank">API settings</a>, optional query string API parameters help you fine-tune embeds.', 'iframely'), Links::link('/settings')); ?>
    <br>
    <?php printf(__('You can override your default Iframely settings for this website with <a href="%s" target="_blank">additional API parameters</a>.', 'iframely'), Links::link('/docs/parameters')); ?>
  </p>

  <table class="form-table" role="presentation">
    <tbody>
    <tr>
      <th scope="row">
        <label for="iframely_api_params"><?php _e('Query string parameters', 'iframely'); ?></label>
      </th>
      <td>
        <input type="text" name="iframely_api_params" id="iframely_api_params" class="regular-text" value="<?php esc_attr_e($api_params); ?>">
        <p class="description"><?php _e('Example', 'iframely'); ?>: <code>id=1&consent=1</code></p>
      </td>
    </tr>
    </tbody>
  </table>

  <?php submit_button(); ?>

</form>
