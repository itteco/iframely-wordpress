<?php

defined('ABSPATH') || exit;

use Iframely\UI\Links;

$iframely_links = [
  [
    'title' => __('Documentation', 'iframely'),
    'tagline' => __('Take a read about third-party rich media, Iframely interactives and content delivery helpers.', 'iframely'),
    'url' => Links::link('/docs'),
  ],
  [
    'title' => __('Plugin discussion', 'iframely'),
    'tagline' => __('We reply to every community post or review on Iframely page at WordPress.org.', 'iframely'),
    'url' => 'https://wordpress.org/plugins/iframely/',
  ],
  [
    'title' => __('Missing a provider?', 'iframely'),
    'tagline' => __('We keep adding new media providers to the service daily. Let us know if we miss the one you need.', 'iframely'),
    'url' => Links::link('/qa/request'),
  ],
  [
    'title' => __('Support', 'iframely'),
    'tagline' => __('Email or website chat, we are just one message away and are here to help along the way.', 'iframely'),
    'url' => Links::link(),
  ],
];
?>

<p class="iframely-mb-intro">
  <?php esc_html_e('Iframely team is very friendly. We want you to get the best rich media experience.', 'iframely'); ?><br>
  <?php esc_html_e('If you run into any difficulties, there are several places you can find help.', 'iframely'); ?><br>
</p>

<?php foreach ($iframely_links as $iframely_link) : ?>
  <h4 class="iframely-mb-05em"><a href="<?php echo esc_url($iframely_link['url']); ?>" target="_blank"><?php echo esc_html($iframely_link['title']); ?></a></h4>
  <p class="iframely-mt-05em iframely-mb-15em"><?php echo esc_html($iframely_link['tagline']); ?></p>
<?php endforeach; ?>
