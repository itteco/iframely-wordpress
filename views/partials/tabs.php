<?php

defined('ABSPATH') || exit;

use Iframely\UI\Links;

?>

<h2 class="nav-tab-wrapper">
  <a href="<?php echo esc_url(Links::tab()); ?>" class="nav-tab <?php if ($tab !== 'customize' && $tab !== 'help'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Enable', 'iframely'); ?></a>
  <a href="<?php echo esc_url(Links::tab('customize')); ?>" class="nav-tab <?php if ($tab === 'customize'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Customize', 'iframely'); ?></a>
  <a href="<?php echo esc_url(Links::tab('help')); ?>" class="nav-tab <?php if ($tab === 'help'): ?>nav-tab-active<?php endif; ?>"><?php esc_html_e('Help & Support', 'iframely'); ?></a>
</h2>
