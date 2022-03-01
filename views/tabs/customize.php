<?php

use Iframely\UI\Links;

$cards = [
  [
    'title' => __('URL cards', 'iframely'),
    'tagline' => __('Cards are previews for URLs without rich media or when media is attached. Design is also applied when you paste and recirculate a link to your own post.', 'iframely'),
    'action' => __('Design URL cards', 'iframely'),
    'url' => Links::link('/settings/cards'),
    'cover' => '',
  ],
  [
    'title' => __('User consents', 'iframely'),
    'tagline' => __('You may request user consent before exposing them to third-party rich media. Optional for GDPR, it brings its own intrinsic privacy value.', 'iframely'),
    'action' => __('Design consents', 'iframely'),
    'url' => Links::link('/settings/consents'),
    'cover' => '',
  ],
  [
    'title' => __('Click-to-play', 'iframely'),
    'tagline' => __('Unify third-party players and MP4 with your Iframely click-to-play cover design, or just activate players lazy-loading with image placeholder.', 'iframely'),
    'action' => __('Stylize click-to-play', 'iframely'),
    'url' => Links::link('/settings/players'),
    'cover' => '',
  ],
  [
    'title' => __('iFrame helpers', 'iframely'),
    'tagline' => __('iFrames do the heavy lifting of rich media rendering, making sure embeds work for you and your user and delivering async speed and all of our HTML.', 'iframely'),
    'action' => __('Customize helpers', 'iframely'),
    'url' => Links::link('/settings/iframes'),
    'cover' => '',
  ],
  [
    'title' => __('Content IDs', 'iframely'),
    'tagline' => __('Short IDs are used as permanent source of iFrames. Unlike API key-based iFrames, Iframely cloud takes care of your content even if you cancel (requires a supporting plan).', 'iframely'),
    'action' => __('Activate IDs', 'iframely'),
    'url' => Links::link('/settings/iframes'),
    'cover' => '',
  ],
  [
    'title' => __('Providers', 'iframely'),
    'tagline' => __('Activate Google Maps, Twitch and configure other providers with most frequent options. For more, contact Iframely support.', 'iframely'),
    'action' => __('Fine-tune providers', 'iframely'),
    'url' => Links::link('/settings/providers'),
    'cover' => '',
  ],
];
?>

<p class="iframely-mb-intro"><?php printf(__('The following features are available in your account\'s dashboard at <a href="%s">iframely.com</a>.', 'iframely'), Links::link()); ?></p>

<div class="iframely-customize">
  <?php foreach ($cards as $card): ?>
    <div class="card iframely-card">
      <div class="iframely-card__body">
        <h2><?php esc_html_e($card['title']); ?></h2>
        <p><?php esc_html_e($card['tagline']); ?></p>
      </div>
      <p class="iframely-card__footer">
        <a href="<?php esc_attr_e($card['url']); ?>" class="button" target="_blank"><?php esc_html_e($card['action']); ?></a>
      </p>
    </div>
  <?php endforeach; ?>
</div>
