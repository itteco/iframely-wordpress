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

    public function test_both_endpoint_definitions_agree(): void
    {
        $this->assertSame(IFRAMELY_API_ENDPOINT, Embed::IFRAMELY_ENDPOINT_URL);
        $this->assertSame(self::ENDPOINT, Embed::IFRAMELY_ENDPOINT_URL);
    }
}
