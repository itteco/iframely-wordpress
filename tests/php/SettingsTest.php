<?php

namespace Iframely\Tests;

use Iframely\Embed\Cache;
use Iframely\Options;
use Iframely\UI\Settings;
use WPDieException;

/**
 * The settings page: saving the form, entering and replacing the API key.
 */
class SettingsTest extends TestCase
{
    public function tear_down(): void
    {
        $GLOBALS['wp_styles'] = null;
        set_current_screen('front');

        parent::tear_down();
    }

    /**
     * Logs in a user who is allowed to manage the plugin settings.
     */
    private function actAsSettingsManager(): void
    {
        $userId = self::factory()->user->create(['role' => 'administrator']);
        if (is_multisite()) {
            grant_super_admin($userId);
        }
        wp_set_current_user($userId);
    }

    /**
     * Fills $_POST like a submitted settings form of the current user.
     *
     * @param array<string, mixed> $fields
     */
    private function submit(array $fields): void
    {
        // WordPress adds slashes to request data, see wp_magic_quotes().
        $_POST = wp_slash($fields + ['iframely_nonce' => wp_create_nonce('iframely_nonce')]);
    }

    /**
     * Runs the save handler and returns what it printed.
     */
    private function save(): string
    {
        ob_start();
        try {
            Settings::save();
        } finally {
            $output = (string)ob_get_clean();
        }
        return $output;
    }

    /**
     * Renders the admin notices queued by the save handler.
     */
    private function notices(): string
    {
        ob_start();
        do_action(is_multisite() ? 'network_admin_notices' : 'admin_notices');
        return (string)ob_get_clean();
    }

    // Guards.

    public function test_requests_without_the_settings_nonce_are_ignored(): void
    {
        $this->actAsSettingsManager();
        $_POST = ['iframely_api_params' => 'theme=dark', 'iframely_cache_refresh' => 'on'];

        $this->assertSame('', $this->save());

        $this->assertSame('', Options::getApiParams());
        $this->assertFalse(Options::isCacheRefreshEnabled());
    }

    public function test_an_invalid_nonce_is_rejected(): void
    {
        $this->actAsSettingsManager();
        $_POST = ['iframely_nonce' => 'invalid', 'iframely_api_params' => 'theme=dark'];

        try {
            $this->save();
            $this->fail('Saving with an invalid nonce should have been stopped.');
        } catch (WPDieException $e) {
            $this->assertStringContainsString('nonce', $e->getMessage());
        }

        $this->assertSame('', Options::getApiParams());
    }

    public function test_users_who_cannot_manage_options_are_rejected(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->submit(['iframely_api_params' => 'theme=dark']);

        try {
            $this->save();
            $this->fail('Saving without the capability should have been stopped.');
        } catch (WPDieException $e) {
            $this->assertStringContainsString('permission', $e->getMessage());
        }

        $this->assertSame('', Options::getApiParams());
    }

    public function test_site_administrators_of_a_network_are_rejected(): void
    {
        $this->skipWithoutMultisite();

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->submit(['iframely_api_params' => 'theme=dark']);

        try {
            $this->save();
            $this->fail('Only network administrators may save the settings on multisite.');
        } catch (WPDieException $e) {
            $this->assertStringContainsString('permission', $e->getMessage());
        }

        $this->assertSame('', Options::getApiParams());
    }

    // Saving the settings form.

    public function test_the_settings_form_is_saved(): void
    {
        $this->actAsSettingsManager();
        $this->submit([
            'iframely_builtins_replace' => 'on',
            'iframely_previews_enhance' => 'on',
            'iframely_cache_refresh' => 'on',
            'iframely_cache_ttl' => (string)(3 * DAY_IN_SECONDS),
            'iframely_api_params' => 'iframe=card&theme=dark',
        ]);

        $this->assertSame('', $this->save());

        $this->assertTrue(Options::isBuiltinsReplaced());
        $this->assertTrue(Options::isPreviewsEnhanced());
        $this->assertTrue(Options::isCacheRefreshEnabled());
        $this->assertSame(3 * DAY_IN_SECONDS, Options::getCacheTtl());
        $this->assertSame('iframe=card&theme=dark', Options::getApiParams());
        $this->assertSame(self::API_KEY, Options::getApiKey(), 'Saving settings must not touch the API key.');
        $this->assertStringContainsString('Settings saved.', $this->notices());
    }

    public function test_unchecked_boxes_switch_their_settings_off(): void
    {
        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);
        Options::setCacheRefresh(true);
        Options::setApiParams('theme=dark');
        $this->actAsSettingsManager();
        $this->submit(['iframely_cache_ttl' => (string)WEEK_IN_SECONDS]);

        $this->save();

        $this->assertFalse(Options::isBuiltinsReplaced());
        $this->assertFalse(Options::isPreviewsEnhanced());
        $this->assertFalse(Options::isCacheRefreshEnabled());
        $this->assertSame('', Options::getApiParams());
    }

    /**
     * @dataProvider queryStringProvider
     */
    public function test_the_extra_query_string_is_kept_as_names_with_encoded_values(string $entered, string $stored): void
    {
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_params' => $entered]);

        $this->save();

        $this->assertSame($stored, Options::getApiParams());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function queryStringProvider(): array
    {
        return [
            'plain parameters stay as they are' => ['iframe=card-small&theme=dark&maxheight=300', 'iframe=card-small&theme=dark&maxheight=300'],
            'encoded values keep their meaning' => ['title=Hello%20World&lang=fr', 'title=Hello%20World&lang=fr'],
            'an encoded slash' => ['callback=a%2Fb', 'callback=a%2Fb'],
            'a space is encoded' => ['title=Hello World', 'title=Hello%20World'],
            'an encoded ampersand stays inside its value' => ['title=a%26b%3Dc&x=1', 'title=a%26b%3Dc&x=1'],
            'quotes are unslashed, markup is removed' => ["title=it's&theme=dark<script>alert(1)</script>", 'title=it%27s&theme=dark'],
            'a leading question mark and spaces around' => [' ?theme=dark ', 'theme=dark'],
            'a parameter without a value' => ['omit_script', 'omit_script='],
            'names that are not plain names are dropped' => ['a[b]=1&c%26d=2&theme=dark', 'theme=dark'],
            'PHP reads a space in a name as an underscore' => ['c d=2', 'c_d=2'],
            'nothing' => ['', ''],
        ];
    }

    public function test_a_cache_lifetime_that_is_not_offered_is_replaced_by_the_default(): void
    {
        $this->actAsSettingsManager();

        foreach (['5', '-1', 'abc', (string)(8 * DAY_IN_SECONDS)] as $value) {
            Options::setCacheTtl(DAY_IN_SECONDS);
            $this->submit(['iframely_cache_refresh' => 'on', 'iframely_cache_ttl' => $value]);
            $this->save();

            $this->assertSame(WEEK_IN_SECONDS, Options::getCacheTtl(), "Lifetime entered: {$value}");
        }
    }

    public function test_every_offered_cache_lifetime_can_be_saved(): void
    {
        $this->actAsSettingsManager();

        foreach (array_keys(Cache::getTtlPresets()) as $value) {
            $this->submit(['iframely_cache_refresh' => 'on', 'iframely_cache_ttl' => (string)$value]);
            $this->save();

            $this->assertSame($value, Options::getCacheTtl());
        }
    }

    public function test_the_api_key_cannot_be_changed_through_the_settings_form(): void
    {
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'anotherkey']);

        $this->save();

        $this->assertSame(self::API_KEY, Options::getApiKey());
        $this->assertSame([], $this->requestedUrls());
    }

    // Entering the API key.

    /**
     * @dataProvider malformedKeyProvider
     */
    public function test_a_malformed_api_key_is_refused_without_calling_the_api(string $key): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => $key]);

        $this->assertSame('', $this->save());

        $this->assertSame('', Options::getApiKey());
        $this->assertSame([], $this->requestedUrls());
        $this->assertStringContainsString('Invalid API key', $this->notices());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function malformedKeyProvider(): array
    {
        return [
            'empty' => [''],
            'with a space' => ['abc 123'],
            'with punctuation' => ['abc-123'],
            'with markup' => ['<b>abc123</b>x!'],
        ];
    }

    public function test_the_api_key_is_verified_with_a_sample_request(): void
    {
        Options::delete('iframely_api_key');
        add_filter('home_url', static function () {
            return 'https://blog.example.com';
        });
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWithEmbed();

        $this->save();

        $this->assertCount(1, $this->requestedUrls());
        $this->assertStringStartsWith(self::ENDPOINT . '?', $this->requestedUrls()[0]);
        $this->assertSame([
            'api_key' => 'abc123',
            'url' => Settings::SAMPLE_URL,
            'origin' => 'blog.example.com',
        ], self::queryOf($this->requestedUrls()[0]));
    }

    public function test_a_verified_api_key_is_stored_and_the_onboarding_is_shown(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWithEmbed();

        $output = $this->save();

        $this->assertSame('abc123', Options::getApiKey());
        $this->assertStringContainsString('iframely-onboarding', $output);
    }

    public function test_the_api_key_is_trimmed_and_lowercased(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => '  ABC123def ']);
        $this->respondWithEmbed();

        $this->save();

        $this->assertSame('abc123def', Options::getApiKey());
        $this->assertSame('abc123def', self::queryOf($this->requestedUrls()[0])['api_key']);
    }

    public function test_an_api_key_rejected_by_the_api_is_not_stored(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWithJson(['status' => 403, 'error' => 'Invalid API key'], 403);

        $this->assertSame('', $this->save());

        $this->assertSame('', Options::getApiKey());
        $notices = $this->notices();
        $this->assertStringContainsString('be verified (error code 403)', $notices);
        $this->assertStringContainsString('notice-error', $notices);
    }

    public function test_an_api_error_without_a_status_gets_a_generic_message(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWithJson(['error' => 'Something went wrong']);

        $this->save();

        $this->assertSame('', Options::getApiKey());
        $notices = $this->notices();
        $this->assertStringContainsString('be verified.', $notices);
        $this->assertStringNotContainsString('error code', $notices);
    }

    public function test_a_failed_connection_does_not_store_the_api_key(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWithError();

        $this->assertSame('', $this->save());

        $this->assertSame('', Options::getApiKey());
        $this->assertStringContainsString('HTTP error', $this->notices());
    }

    /**
     * @dataProvider notAnAnswerProvider
     */
    public function test_an_answer_that_does_not_come_from_the_api_does_not_store_the_api_key(string $body, int $code, string $expected, string $unexpected): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();
        $this->submit(['iframely_api_key' => 'abc123']);
        $this->respondWith(static function () use ($body, $code) {
            return self::httpResponse($body, $code);
        });

        $output = $this->save();

        $this->assertSame('', Options::getApiKey());
        $this->assertStringNotContainsString('iframely-onboarding', $output);
        $this->assertStringContainsString($expected, $this->notices());
        $this->assertStringNotContainsString($unexpected, $this->notices());
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string, 3: string}>
     */
    public function notAnAnswerProvider(): array
    {
        return [
            'error page of a proxy' => ['<html><body>502 Bad Gateway</body></html>', 502, 'be verified (error code 502)', 'onboarding'],
            'sign-in page of a network' => ['<html><body>Please sign in</body></html>', 200, 'be verified.', 'error code'],
            'empty answer' => ['', 200, 'be verified.', 'error code'],
            'text that is JSON but not data' => ['"OK"', 200, 'be verified.', 'error code'],
            'data with a failure status' => ['{"type":"rich"}', 503, 'be verified (error code 503)', 'onboarding'],
            'error of the API on a failure status' => ['{"status":403,"error":"Invalid API key"}', 403, 'be verified (error code 403)', 'onboarding'],
        ];
    }

    // Replacing the API key.

    public function test_asking_to_change_the_api_key_starts_the_reactivation(): void
    {
        $this->actAsSettingsManager();
        $this->submit(['iframely_reactivation_request' => '1', 'iframely_api_params' => 'theme=dark']);

        $this->assertSame('', $this->save());

        $this->assertTrue(Options::getReactivationFlag());
        $this->assertSame(self::API_KEY, Options::getApiKey());
        $this->assertSame('', Options::getApiParams(), 'Nothing else is saved by this request.');
        $this->assertStringContainsString('Please enter new API key.', $this->notices());
    }

    public function test_a_new_verified_api_key_replaces_the_old_one(): void
    {
        Options::setReactivationFlag();
        $this->actAsSettingsManager();
        $this->submit(['iframely_reactivation' => '1', 'iframely_api_key' => 'newkey123']);
        $this->respondWithEmbed();

        $this->assertSame('', $this->save(), 'The onboarding is only shown for the first key.');

        $this->assertSame('newkey123', Options::getApiKey());
        $this->assertFalse(Options::getReactivationFlag());
        $this->assertStringContainsString('API key has been updated.', $this->notices());
    }

    public function test_the_old_api_key_is_kept_when_the_new_one_is_rejected(): void
    {
        Options::setReactivationFlag();
        $this->actAsSettingsManager();
        $this->submit(['iframely_reactivation' => '1', 'iframely_api_key' => 'newkey123']);
        $this->respondWithJson(['status' => 403, 'error' => 'Invalid API key'], 403);

        $this->save();

        $this->assertSame(self::API_KEY, Options::getApiKey());
        $this->assertTrue(Options::getReactivationFlag(), 'The user can try again.');
    }

    public function test_a_reactivation_form_without_a_started_reactivation_only_saves_settings(): void
    {
        $this->actAsSettingsManager();
        $this->submit(['iframely_reactivation' => '1', 'iframely_api_key' => 'newkey123']);

        $this->save();

        $this->assertSame(self::API_KEY, Options::getApiKey());
        $this->assertSame([], $this->requestedUrls());
    }

    // Page rendering.

    public function test_the_activation_form_is_shown_until_an_api_key_is_stored(): void
    {
        Options::delete('iframely_api_key');
        $this->actAsSettingsManager();

        ob_start();
        Settings::render();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('Activate Iframely', $html);
        $this->assertStringContainsString('name="iframely_api_key"', $html);
        $this->assertStringContainsString('name="iframely_nonce"', $html);
        $this->assertStringNotContainsString('nav-tab-wrapper', $html);
    }

    public function test_the_settings_form_is_shown_once_an_api_key_is_stored(): void
    {
        Options::setCacheRefresh(true);
        Options::setCacheTtl(2 * WEEK_IN_SECONDS);
        Options::setApiParams('theme=dark');
        $this->actAsSettingsManager();
        // The links on the page need the page to be registered.
        Settings::register();

        ob_start();
        Settings::render();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('nav-tab-wrapper', $html);
        $this->assertStringContainsString('name="iframely_builtins_replace"', $html);
        $this->assertStringContainsString('name="iframely_previews_enhance"', $html);
        $this->assertMatchesRegularExpression('/name="iframely_cache_refresh"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . (2 * WEEK_IN_SECONDS) . '"\s+selected/', $html);
        $this->assertStringContainsString('value="theme=dark"', $html);
        $this->assertStringContainsString('value="' . self::API_KEY . '"', $html);
        $this->assertStringContainsString('page=iframely&action=reactivate', $html);
    }

    public function test_changing_the_api_key_asks_for_confirmation_first(): void
    {
        $this->actAsSettingsManager();
        $_GET['action'] = 'reactivate';

        ob_start();
        Settings::render();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('name="iframely_reactivation_request"', $html);
        $this->assertStringContainsString('name="iframely_nonce"', $html);
        $this->assertStringNotContainsString('name="iframely_cache_refresh"', $html);
    }

    public function test_the_activation_form_is_shown_while_the_api_key_is_being_changed(): void
    {
        Options::setReactivationFlag();
        $this->actAsSettingsManager();
        $_POST['iframely_reactivation'] = '1';

        ob_start();
        Settings::render();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString('Activate Iframely', $html);
        $this->assertStringContainsString('name="iframely_reactivation"', $html);
        $this->assertStringContainsString('name="iframely_api_key"', $html);
    }

    /**
     * @dataProvider tabProvider
     */
    public function test_the_selected_tab_is_rendered(string $tab, string $expected, string $unexpected): void
    {
        $this->actAsSettingsManager();
        $_GET['tab'] = $tab;

        ob_start();
        Settings::render();
        $html = (string)ob_get_clean();

        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString($unexpected, $html);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function tabProvider(): array
    {
        return [
            'default tab' => ['', 'name="iframely_cache_refresh"', 'class="iframely-customize"'],
            'unknown tab falls back to the default' => ['nope', 'name="iframely_cache_refresh"', 'class="iframely-customize"'],
            'customize tab' => ['customize', 'class="iframely-customize"', 'name="iframely_cache_refresh"'],
            'help tab' => ['help', '&tab=help" class="nav-tab nav-tab-active"', 'name="iframely_cache_refresh"'],
        ];
    }

    public function test_the_page_is_registered_under_the_settings_menu(): void
    {
        $this->actAsSettingsManager();

        Settings::register();

        $this->assertStringContainsString('page=iframely', menu_page_url('iframely', false));
    }

    public function test_the_admin_stylesheet_is_only_loaded_on_the_settings_page(): void
    {
        set_current_screen('dashboard');
        Settings::enqueue();
        $this->assertFalse(wp_style_is('iframely-admin', 'enqueued'));

        set_current_screen('settings_page_iframely');
        Settings::enqueue();
        $this->assertTrue(wp_style_is('iframely-admin', 'enqueued'));
        $this->assertSame(IFRAMELY_PLUGIN_URL . 'build/admin.css', wp_styles()->registered['iframely-admin']->src);
    }

    public function test_the_block_editor_stylesheet_is_enqueued(): void
    {
        Settings::enqueueEditor();

        $this->assertTrue(wp_style_is('iframely-options', 'enqueued'));
        $this->assertSame(IFRAMELY_PLUGIN_URL . 'build/index.css', wp_styles()->registered['iframely-options']->src);
    }
}
