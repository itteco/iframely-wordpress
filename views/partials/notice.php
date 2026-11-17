<?php defined('ABSPATH') || exit; ?>
<div class="notice notice-<?php echo esc_attr($status); ?> <?php if ($dismissible): ?>is-dismissible<?php endif; ?>">
  <p><?php echo wp_kses_post($message); ?></p>
</div>
