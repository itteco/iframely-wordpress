<?php
use Iframely\Plugin;
use Iframely\Reactivation;
?>

<div class="wrap">
  <h1 class="iframely-heading"><?php _e('Iframely – media embed blocks', 'iframely'); ?></h1>
  <?php Plugin::view('partials/tabs', $data); ?>
  <?php
    if ($tab === 'help') {
      Plugin::view('tabs/help', $data);
    }
    elseif ($tab === 'customize') {
      Plugin::view('tabs/customize', $data);
    }
    elseif (Reactivation::isTab()) {
      Plugin::view('tabs/reactivate', $data);
    }
    else {
      Plugin::view('tabs/settings', $data);
    }
  ?>
</div>
