<?php

defined('ABSPATH') || exit;

use Iframely\UI\Links;

?>

<form method="post" action="<?php echo esc_url(Links::settings()); ?>" novalidate="novalidate">
  <?php wp_nonce_field('iframely_nonce', 'iframely_nonce'); ?>
  <input type="hidden" name="iframely_reactivation_request" id="iframely_reactivation_request" value="1">
  <p class="iframely-mb-intro">
    <?php esc_html_e('Your current API key stays in use until a new one is verified. Embeds in your posts switch to the new key the next time they are refreshed.', 'iframely'); ?>
  </p>
  <?php submit_button(__('Change API key', 'iframely')); ?>
</form>
