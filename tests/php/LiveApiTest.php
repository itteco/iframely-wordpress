<?php

namespace Iframely\Tests;

use Iframely\Embed;
use Iframely\Options;
use Iframely\UI\Settings;
use WP_UnitTestCase;

/**
 * Smoke test against the real Iframely API. Everything else in this suite mocks
 * HTTP; this class checks that the mocks still describe what the API really does.
 *
 * It is excluded from normal runs. Run it with `npm run test:php:live`, which
 * needs an API key in the IFRAMELY_API_KEY environment variable.
 *
 * @group live
 */
class LiveApiTest extends WP_UnitTestCase
{
    /**
     * @var string
     */
    private $key;

    public function set_up(): void
    {
        parent::set_up();

        $this->key = strtolower(trim((string)getenv('IFRAMELY_API_KEY')));
        if ($this->key === '') {
            $this->markTestSkipped('IFRAMELY_API_KEY is not set.');
        }

        $userId = self::factory()->user->create(['role' => 'administrator']);
        if (is_multisite()) {
            grant_super_admin($userId);
        }
        wp_set_current_user($userId);
        Options::delete('iframely_api_key');
    }

    public function tear_down(): void
    {
        $_POST = [];

        parent::tear_down();
    }

    private function submitKey(string $key): string
    {
        $_POST = wp_slash(['iframely_nonce' => wp_create_nonce('iframely_nonce'), 'iframely_api_key' => $key]);

        ob_start();
        try {
            Settings::save();
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }

    public function test_the_real_api_accepts_the_key_on_activation(): void
    {
        $output = $this->submitKey($this->key);

        $this->assertSame($this->key, Options::getApiKey(), 'The Iframely API did not accept the key.');
        $this->assertStringContainsString('iframely-onboarding', $output);
    }

    public function test_the_real_api_rejects_an_unknown_key_on_activation(): void
    {
        $this->submitKey('0000000000000000000000');

        $this->assertSame('', Options::getApiKey());

        ob_start();
        do_action(is_multisite() ? 'network_admin_notices' : 'admin_notices');
        $notices = (string)ob_get_clean();
        $this->assertStringContainsString('be verified', $notices);
    }

    public function test_the_real_api_returns_embed_html(): void
    {
        Options::setApiKey($this->key);

        $data = _wp_oembed_get_object()->fetch(
            Embed::createApiLink(),
            'https://www.youtube.com/watch?v=jNQXAC9IVRw',
            ['width' => 600, 'height' => 400]
        );

        $this->assertIsObject($data, 'The Iframely API returned no oEmbed data.');
        $this->assertStringContainsString('<iframe', $data->html);
    }

    /**
     * The card tests (CardTest, tests/e2e/cards.spec.js) rest on these two facts.
     */
    public function test_the_real_api_returns_a_card_with_the_small_card_option_for_a_page(): void
    {
        Options::setApiKey($this->key);

        $data = _wp_oembed_get_object()->fetch(
            Embed::createApiLink(),
            'https://en.wikipedia.org/wiki/Tiramisu',
            ['width' => 600, 'height' => 400]
        );

        $this->assertIsObject($data, 'The Iframely API returned no oEmbed data.');
        $this->assertStringContainsString('iframely', $data->html);
        $this->assertStringNotContainsString('card=small', $data->html);
        $this->assertSame('', $data->options->card->value);
        $this->assertObjectHasProperty('small', $data->options->card->values);
    }

    public function test_the_real_api_returns_a_small_card_for_the_option_saved_in_the_url(): void
    {
        Options::setApiKey($this->key);

        // The URL the editor saves when "Make it a small card" is chosen.
        $data = _wp_oembed_get_object()->fetch(
            Embed::createApiLink(),
            'https://en.wikipedia.org/wiki/Tiramisu?iframely=eyJjYXJkIjoic21hbGwifQ%3D%3D',
            ['width' => 600, 'height' => 400]
        );

        $this->assertIsObject($data, 'The Iframely API returned no oEmbed data.');
        $this->assertStringContainsString('card=small', $data->html);
        $this->assertSame('small', $data->options->card->value);
    }

    public function test_the_real_api_turns_off_an_option_that_is_on_by_default(): void
    {
        Options::setApiKey($this->key);
        $track = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT';

        $default = _wp_oembed_get_object()->fetch(Embed::createApiLink(), $track, ['width' => 600, 'height' => 400]);
        // The URL the editor saves when "Slimmer horizontal player" is unchecked.
        $changed = _wp_oembed_get_object()->fetch(
            Embed::createApiLink(),
            $track . '?iframely=' . rawurlencode(base64_encode('{"_compact":false}')),
            ['width' => 600, 'height' => 400]
        );

        $this->assertIsObject($default, 'The Iframely API returned no oEmbed data.');
        $this->assertIsObject($changed, 'The Iframely API returned no oEmbed data.');
        $this->assertTrue($default->options->_compact->value);
        $this->assertFalse($changed->options->_compact->value);
    }
}
