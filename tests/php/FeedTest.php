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

    public function test_no_item_of_a_feed_gets_an_iframely_embed(): void
    {
        $content = "Intro\n\n" . self::URL . "\n\nOutro";
        $first = self::factory()->post->create(['post_content' => $content]);
        $second = self::factory()->post->create(['post_content' => $content]);
        $this->respondWithEmbed();
        $this->go_to('/?feed=rss2');
        $this->assertTrue(is_feed());

        $this->enterPost($first);
        $firstItem = get_the_content_feed('rss2');
        $this->enterPost($second);
        $secondItem = get_the_content_feed('rss2');

        foreach ([$firstItem, $secondItem] as $item) {
            $this->assertStringNotContainsString(self::EMBED_HTML, $item);
            $this->assertStringContainsString(self::URL, $item);
        }
        $this->assertCount(0, $this->iframelyRequests());
    }

    public function test_the_excerpt_of_a_feed_item_gets_no_iframely_embed_either(): void
    {
        $post = self::factory()->post->create(['post_content' => "Intro\n\n" . self::URL . "\n\nOutro", 'post_excerpt' => '']);
        $this->respondWithEmbed();
        $this->go_to('/?feed=rss2');

        $this->enterPost($post);
        ob_start();
        the_excerpt_rss();
        $excerpt = (string)ob_get_clean();

        $this->assertStringContainsString('Intro', $excerpt);
        $this->assertCount(0, $this->iframelyRequests());
    }

    public function test_posts_outside_of_a_feed_keep_their_iframely_embeds(): void
    {
        $post = self::factory()->post->create(['post_content' => "Intro\n\n" . self::URL . "\n\nOutro"]);
        $this->respondWithEmbed();
        $this->go_to(get_permalink($post));
        $this->assertFalse(is_feed());

        $this->enterPost($post);
        $html = apply_filters('the_content', get_the_content());

        $this->assertStringContainsString(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->iframelyRequests());
    }
}
