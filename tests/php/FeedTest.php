<?php

namespace Iframely\Tests;

use Iframely\Embed\Feed;

/**
 * RSS feeds: embeds need JavaScript, so Iframely is meant to stay out of feeds.
 */
class FeedTest extends TestCase
{
    private const URL = 'https://example.com/some/article';

    public function tear_down(): void
    {
        wp_reset_postdata();
        $GLOBALS['wp_embed']->post_ID = null;

        parent::tear_down();
    }

    public function test_feed_content_passes_through_unchanged(): void
    {
        $this->assertSame('<p>Content</p>', Feed::iframely_disable_on_feed('<p>Content</p>'));
    }

    public function test_feed_content_removes_the_catch_all_provider(): void
    {
        $this->assertArrayHasKey(self::CATCH_ALL_PROVIDER, _wp_oembed_get_object()->providers);

        Feed::iframely_disable_on_feed('<p>Content</p>');

        $this->assertArrayNotHasKey(self::CATCH_ALL_PROVIDER, _wp_oembed_get_object()->providers);
        $this->assertArrayHasKey(self::SHORT_LINK_PROVIDER, _wp_oembed_get_object()->providers);
    }

    public function test_feed_content_gives_embeds_a_separate_cache_key(): void
    {
        Feed::iframely_disable_on_feed('<p>Content</p>');

        $this->assertSame(1, wp_embed_defaults(self::URL)['feed']);
    }

    public function test_feed_marker_is_added_to_embed_arguments(): void
    {
        $this->assertSame(['width' => 500, 'feed' => 1], Feed::iframely_add_feed_arg(['width' => 500]));
    }

    /**
     * Known issue, kept as a record of current behaviour (see the review report):
     * the_content_feed runs after the_content has already rendered the embeds of
     * the current item, so Iframely is only switched off from the second feed item
     * on. The first item of every feed still gets an Iframely embed.
     */
    public function test_known_issue_only_items_after_the_first_are_left_without_iframely_embeds(): void
    {
        $content = "Intro\n\n" . self::URL . "\n\nOutro";
        $first = self::factory()->post->create(['post_content' => $content]);
        $second = self::factory()->post->create(['post_content' => $content]);
        $this->respondWithEmbed();

        $this->enterPost($first);
        $firstItem = get_the_content_feed('rss2');
        $this->enterPost($second);
        $secondItem = get_the_content_feed('rss2');

        $this->assertStringContainsString(self::EMBED_HTML, $firstItem);
        $this->assertStringNotContainsString(self::EMBED_HTML, $secondItem);
        $this->assertStringContainsString(self::URL, $secondItem);
        $this->assertCount(1, $this->iframelyRequests());
    }
}
