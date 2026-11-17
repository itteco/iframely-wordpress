<?php defined('ABSPATH') || exit; ?>
<div class="notice notice-<?php echo esc_attr($status); ?><?php echo $dismissible ? ' is-dismissible' : ''; ?>">
  <p><?php echo wp_kses_post($message); ?></p>
</div>
