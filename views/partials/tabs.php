<?php use Iframely\UI\Links; ?>

<h2 class="nav-tab-wrapper">
  <a href="<?php echo Links::tab(); ?>" class="nav-tab <?php if ($tab !== 'customize' && $tab !== 'help'): ?>nav-tab-active<?php endif; ?>"><?php _e('Enable', 'iframely'); ?></a>
  <a href="<?php echo Links::tab('customize'); ?>" class="nav-tab <?php if ($tab === 'customize'): ?>nav-tab-active<?php endif; ?>"><?php _e('Customize', 'iframely'); ?></a>
  <a href="<?php echo Links::tab('help'); ?>" class="nav-tab <?php if ($tab === 'help'): ?>nav-tab-active<?php endif; ?>"><?php _e('Help & Support', 'iframely'); ?></a>
</h2>
