<?php use Iframely\UI\Links; ?>

<form method="post" action="<?php echo Links::settings(); ?>" novalidate="novalidate">
  <?php wp_nonce_field('iframely_nonce', 'iframely_nonce'); ?>
  <input type="hidden" name="iframely_reactivation_request" id="iframely_reactivation_request" value="1">
  <p class="iframely-mb-intro">
    <?php _e('When you change your API key, the media cache in your posts is cleared. You will need a new valid key to reinstate the embedded content. Are you sure?', 'iframely'); ?>
  </p>
  <?php submit_button(__('Change API key', 'iframely')); ?>
</form>
