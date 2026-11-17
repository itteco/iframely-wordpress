<?php

namespace Iframely\Tests;

class ShortcodeTest extends TestCase
{
    public function test_shortcode_is_registered(): void
    {
        $this->assertTrue(shortcode_exists('iframely'));
    }

    public function test_embeds_the_enclosed_url_through_iframely(): void
    {
        $this->respondWithEmbed();

        $html = do_shortcode('[iframely]https://example.com/some/article[/iframely]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertCount(1, $this->iframelyRequests());
        $this->assertSame('https://example.com/some/article', self::queryOf($this->iframelyRequests()[0])['url']);
    }

    /**
     * @dataProvider urlAttributeProvider
     */
    public function test_embeds_the_url_attribute_with_ampersands_decoded(string $attribute): void
    {
        $this->respondWithEmbed();

        $html = do_shortcode('[iframely url="' . $attribute . '"]');

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertSame('https://example.com/watch?a=1&b=2', self::queryOf($this->iframelyRequests()[0])['url']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function urlAttributeProvider(): array
    {
        return [
            'plain ampersand' => ['https://example.com/watch?a=1&b=2'],
            'named entity' => ['https://example.com/watch?a=1&amp;b=2'],
            'numeric entity' => ['https://example.com/watch?a=1&#038;b=2'],
            'surrounding whitespace' => [' https://example.com/watch?a=1&b=2 '],
        ];
    }

    public function test_falls_back_to_a_link_when_iframely_has_no_embed(): void
    {
        $this->respondWithJson(['error' => 'Not found', 'status' => 404], 404);

        $html = do_shortcode('[iframely]https://example.com/missing[/iframely]');

        $this->assertSame('<a href="https://example.com/missing">https://example.com/missing</a>', $html);
    }
}
