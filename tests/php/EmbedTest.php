<?php

namespace Iframely\Tests;

use Iframely\Embed;
use Iframely\Options;

/**
 * Building of the Iframely API URL that is registered as the oEmbed provider.
 */
class EmbedTest extends TestCase
{
    public function test_api_link_points_at_the_oembed_endpoint_with_the_api_key(): void
    {
        $link = Embed::createApiLink();

        $this->assertStringStartsWith(self::ENDPOINT . '?', $link);
        $this->assertSame(self::API_KEY, self::queryOf($link)['api_key']);
    }

    public function test_api_link_sends_the_site_address_without_scheme_as_origin(): void
    {
        add_filter('home_url', static function () {
            return 'https://blog.example.com/news';
        });

        $this->assertSame('blog.example.com/news', self::queryOf(Embed::createApiLink())['origin']);
    }

    public function test_api_link_has_no_extra_parameters_by_default(): void
    {
        $this->assertSame(['origin', 'api_key'], array_keys(self::queryOf(Embed::createApiLink())));
    }

    public function test_api_link_appends_the_extra_query_string_from_the_settings(): void
    {
        Options::setApiParams('iframe=card&theme=dark&omit_script=1');

        $query = self::queryOf(Embed::createApiLink());

        $this->assertSame('card', $query['iframe']);
        $this->assertSame('dark', $query['theme']);
        $this->assertSame('1', $query['omit_script']);
        $this->assertSame(self::API_KEY, $query['api_key']);
    }

    public function test_api_link_encodes_the_values_of_the_extra_query_string(): void
    {
        // As the settings form stores it, and as older versions may have left it.
        foreach (['title=a%26b%3Dc&x=1%232&t=Hello%20World', 'title=a%26b%3Dc&x=1%232&t=Hello World'] as $stored) {
            Options::setApiParams($stored);

            $link = Embed::createApiLink();

            $this->assertStringEndsWith('&title=a%26b%3Dc&x=1%232&t=Hello%20World', $link);
            $query = self::queryOf($link);
            $this->assertSame('a&b=c', $query['title']);
            $this->assertSame('1#2', $query['x']);
            $this->assertSame(self::API_KEY, $query['api_key']);
        }
    }

    public function test_the_extra_query_string_goes_from_the_form_to_the_api_request_unchanged(): void
    {
        Options::setApiParams(\Iframely\UI\Settings::sanitizeApiParams('title=Hello%20World&callback=a%2Fb&iframe=card-small'));

        $query = self::queryOf(Embed::createApiLink());

        $this->assertSame('Hello World', $query['title']);
        $this->assertSame('a/b', $query['callback']);
        $this->assertSame('card-small', $query['iframe']);
    }

    public function test_both_endpoint_definitions_agree(): void
    {
        $this->assertSame(IFRAMELY_API_ENDPOINT, Embed::IFRAMELY_ENDPOINT_URL);
        $this->assertSame(self::ENDPOINT, Embed::IFRAMELY_ENDPOINT_URL);
    }
}
