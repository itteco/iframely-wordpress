<?php

namespace Iframely\Tests;

use Iframely\Embed\Amp;
use Iframely\Options;

/**
 * Compatibility with the AMP plugin.
 */
class AmpTest extends TestCase
{
    private const PROVIDER = self::ENDPOINT . '?origin=example.org&api_key=k';
    private const AMP_IFRAME = '<amp-iframe src="https://cdn.iframe.ly/x" width="16" height="9"><div overflow></div></amp-iframe>';

    public function test_recognises_amp_requests_by_their_embed_arguments(): void
    {
        $this->assertTrue(Amp::is_iframely_amp(['iframely' => 'amp']));
        $this->assertTrue(Amp::is_iframely_amp('width=500&iframely=amp'));

        $this->assertFalse(Amp::is_iframely_amp(['iframely' => 'other']));
        $this->assertFalse(Amp::is_iframely_amp(['width' => 500]));
        $this->assertFalse(Amp::is_iframely_amp('width=500'));
        $this->assertFalse(Amp::is_iframely_amp(null));
    }

    public function test_asks_iframely_for_amp_markup_on_amp_requests(): void
    {
        $result = Amp::maybe_add_iframe_amp(self::PROVIDER, 'https://example.com/a', ['iframely' => 'amp']);

        $this->assertSame('1', self::queryOf($result)['amp']);
    }

    public function test_does_not_ask_for_amp_markup_otherwise(): void
    {
        $this->assertSame(self::PROVIDER, Amp::maybe_add_iframe_amp(self::PROVIDER, 'https://example.com/a', []));

        $other = 'https://www.youtube.com/oembed';
        $this->assertSame($other, Amp::maybe_add_iframe_amp($other, 'https://example.com/a', ['iframely' => 'amp']));
    }

    public function test_regular_embed_html_does_not_change_paragraph_handling(): void
    {
        $html = Amp::iframely_filter_oembed_result(self::EMBED_HTML, 'https://example.com/a', []);

        $this->assertSame(self::EMBED_HTML, $html);
        $this->assertSame(10, has_filter('the_content', 'wpautop'));
        $this->assertFalse(has_filter('the_content', [Amp::class, 'iframely_autop_on_amp']));
    }

    public function test_amp_embed_html_swaps_wpautop_for_an_amp_aware_version(): void
    {
        $html = Amp::iframely_filter_oembed_result(self::AMP_IFRAME, 'https://example.com/a', []);

        $this->assertSame(self::AMP_IFRAME, $html);
        $this->assertFalse(has_filter('the_content', 'wpautop'));
        $this->assertSame(1000, has_filter('the_content', [Amp::class, 'iframely_autop_on_amp']));
    }

    public function test_amp_aware_autop_wraps_text_but_leaves_amp_iframes_intact(): void
    {
        $content = "First paragraph.\n\nSecond paragraph.\n\n" . self::AMP_IFRAME;

        $result = Amp::iframely_autop_on_amp($content);

        $this->assertStringContainsString('<p>First paragraph.</p>', $result);
        $this->assertStringContainsString('<p>Second paragraph.</p>', $result);
        $this->assertStringContainsString(self::AMP_IFRAME, $result);
        $this->assertStringNotContainsString('<p><amp-iframe', $result);
    }

    /**
     * Known issue, kept as a record of current behaviour (see the review report):
     * whatever follows the last amp-iframe is treated as malformed markup and is
     * returned without paragraph tags.
     */
    public function test_known_issue_text_after_the_last_amp_iframe_gets_no_paragraphs(): void
    {
        $content = "Before.\n\n" . self::AMP_IFRAME . "\n\nAfter one.\n\nAfter two.";

        $result = Amp::iframely_autop_on_amp($content);

        $this->assertStringContainsString('<p>Before.</p>', $result);
        $this->assertStringEndsWith(self::AMP_IFRAME . "\n\nAfter one.\n\nAfter two.", $result);
    }

    public function test_amp_aware_autop_keeps_every_amp_iframe_of_the_content(): void
    {
        $content = self::AMP_IFRAME . "\n\nBetween.\n\n" . self::AMP_IFRAME;

        $result = Amp::iframely_autop_on_amp($content);

        $this->assertSame(2, substr_count($result, self::AMP_IFRAME));
        $this->assertStringContainsString('<p>Between.</p>', $result);
    }

    public function test_amp_aware_autop_behaves_like_wpautop_without_amp_iframes(): void
    {
        $content = "First paragraph.\n\nSecond paragraph.";

        $this->assertSame(wpautop($content), Amp::iframely_autop_on_amp($content));
    }

    public function test_amp_plugin_embed_handlers_are_disabled_when_builtins_are_replaced(): void
    {
        $handlers = ['AMP_YouTube_Embed_Handler' => [], 'AMP_Twitter_Embed_Handler' => []];

        Options::setBuiltinsReplace(true);
        $this->assertSame([], Amp::maybe_disable_default_embed_handlers($handlers));

        Options::setBuiltinsReplace(false);
        $this->assertSame($handlers, Amp::maybe_disable_default_embed_handlers($handlers));
    }
}
