<?php
use Iframely\Reactivation;
use Iframely\UI\Links;
?>

<div class="wrap">

  <h1><?php _e('Activate Iframely', 'iframely'); ?></h1>

  <?php if (empty($_POST['iframely_nonce'])): ?>
    <p class="iframely-mb-15em"><?php printf(__('In order to use Iframely service, you need an API key from <a href="%s" target="_blank">iframely.com</a>.', 'iframely'), Links::link()); ?></p>
  <?php endif; ?>

  <div class="iframely-activation">
    <form method="post" action="" class="card iframely-card iframely-activation__card">
      <div class="iframely-card__body">
        <h2 class="title"><?php _e('I already have an API key', 'iframely'); ?></h2>
        <p><?php printf(__('Enter your API key to connect this website with Iframely cloud API. You can find and manage API keys in your dashboard at <a href="%s" target="_blank">iframely.com</a>.', 'iframely'), Links::link()) ?></p>
        <?php wp_nonce_field('iframely_nonce', 'iframely_nonce'); ?>
      </div>
      <div class="iframely-activation__form">
        <?php if (Reactivation::isRequest() || Reactivation::inProgress()): ?>
          <input type="hidden" name="iframely_reactivation" id="iframely_reactivation" value="1">
        <?php endif; ?>
        <input type="text" name="iframely_api_key" id="iframely_api_key" class="iframely-activation__input" placeholder="<?php _e('Enter your API key', 'iframely'); ?>" required>
        <input type="submit" name="submit" id="submit" class="button button-primary iframely-activation__button" value="<?php _e('Connect with API key', 'iframely') ?>">
      </div>
    </form>

    <div class="card iframely-card iframely-activation__card">
      <div class="iframely-card__body">
        <h2 class="title"><?php _e('I’m new to Iframely', 'iframely'); ?></h2>
        <p><?php printf(__('Register at <a href="%s" target="_blank">iframely.com</a> and get free full-featured 30 days trial period. Iframely also offers a free and limited "Developer" plan for development and testing purposes.', 'iframely'), Links::link()) ?></p>
      </div>
      <div class="iframely-activation__form">
        <a href="<?php echo Links::link('/signup'); ?>" class="button iframely-activation__button" target="_blank"><?php _e('Register and get API key', 'iframely'); ?></a>
      </div>
    </div>

  </div>

  <?php Iframely\Plugin::view('partials/promo'); ?>

</div>
