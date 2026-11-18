<?php

namespace Iframely;

use Iframely\Embed\Cache;

class Upgrade
{
    public static function run(): void
    {
        if (Options::getVersion() === 1 || Options::isAllSet()) {
            return;
        }

        // Cache
        $ttl = Options::get('iframely_cache_ttl');
        if ($ttl !== false) {
            $ttl = (int)$ttl;
            if ($ttl > 0) {
                Options::setCacheRefresh(true);
                $value = $ttl * DAY_IN_SECONDS;
                # This runs while the plugin is loaded, before translations can be used
                if (!in_array($value, Cache::getTtlValues(), true)) {
                    $value = Cache::getDefaultTtlValue();
                }
                Options::setCacheTtl($value);
            } else {
                Options::setCacheRefresh(false);
            }
        }

        // Replace default providers
        $shortcode = Options::get('iframely_only_shortcode');
        if ($shortcode !== false) {
            Options::setBuiltinsReplace(!(int)$shortcode);
            Options::delete('iframely_only_shortcode');
        }

        // Site previews
        $previews = Options::get('publish_iframely_cards');
        if ($previews !== false) {
            Options::setPreviewsEnhance((bool)(int)$previews);
            Options::delete('publish_iframely_cards');
        }

        Options::setVersion(1);

        if (Plugin::isActivated()) {
            Options::setAllSet(true);
        }
    }
}
