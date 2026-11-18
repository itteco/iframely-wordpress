<?php defined('ABSPATH') || exit; ?>
<div class="notice is-dismissible iframely-onboarding">

  <h2 class="iframely-onboarding__title"><?php esc_html_e('Enjoy your new embeds!', 'iframely'); ?></h2>

  <div class="iframely-onboarding__body">
    <div class="iframely-onboarding__text">

      <h3><?php esc_html_e('How to use Iframely?', 'iframely'); ?></h3>
      <ol>
        <li><?php esc_html_e('Just enter or paste a link in Gutenberg as you usually do. Iframely will detect a link and replace it with a rich media embed.', 'iframely'); ?></li>
        <li><?php esc_html_e('Use embed options in the sidebar to fine-tune your embeds.', 'iframely'); ?></li>
      </ol>
      <h3 class="iframely-mt-15em"><?php esc_html_e('Next steps', 'iframely'); ?></h3>
      <p><?php esc_html_e('You change every aspect of Iframely via settings. Configure what you get and how you get it. Match cards and click-to-play to your branding.', 'iframely'); ?></p>

    </div>
    <div class="iframely-onboarding__cover">

      <div class="iframely-onboarding__embed">
        <picture>
          <source srcset="<?php echo esc_url(IFRAMELY_PLUGIN_URL . 'assets/onboarding.avif'); ?> 1x, <?php echo esc_url(IFRAMELY_PLUGIN_URL . 'assets/onboarding-2x.avif'); ?> 1.5x" type="image/avif">
          <source srcset="<?php echo esc_url(IFRAMELY_PLUGIN_URL . 'assets/onboarding.png'); ?> 1x, <?php echo esc_url(IFRAMELY_PLUGIN_URL . 'assets/onboarding-2x.png'); ?> 1.5x">
          <img src="<?php echo esc_url(IFRAMELY_PLUGIN_URL . 'assets/onboarding.png'); ?>" width="676" height="420" class="iframely-onboarding__img" alt="<?php esc_attr_e('Enjoy your new embeds!', 'iframely'); ?>">
        </picture>
      </div>

    </div>
  </div>

</div>
