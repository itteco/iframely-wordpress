<?php

namespace Iframely;

class Options
{
    public static function get(string $name)
    {
        return is_multisite() ? get_site_option($name) : get_option($name);
    }

    public static function update(string $name, $value): bool
    {
        return is_multisite() ? update_site_option($name, $value) : update_option($name, $value);
    }

    public static function delete(string $name): bool
    {
        return is_multisite() ? delete_site_option($name) : delete_option($name);
    }

    public static function collect(): array
    {
        return [
            'api_key' => self::getApiKey(),
            'api_params' => self::getApiParams(),
            'cache_refresh' => self::getCacheRefresh(),
            'cache_ttl' => self::getCacheTtl(),
            'builtins_replace' => self::getBuiltinsReplace(),
            'previews_enhance' => self::getPreviewsEnhance(),
        ];
    }

    public static function reset(): void
    {
        self::delete('iframely_api_key');
        self::delete('iframely_api_params');
        self::delete('iframely_cache_refresh');
        self::delete('iframely_cache_ttl');
        self::delete('iframely_builtins_replace');
        self::delete('iframely_previews_enhance');
        self::delete('iframely_version');
        self::delete('iframely_all_set');
        self::delete('iframely_reactivation');
    }

    //<editor-fold desc="Version">
    public static function getVersion(): int
    {
        return (int)self::get('iframely_version');
    }

    public static function setVersion(int $value): bool
    {
        return self::update('iframely_version', $value);
    }
    //</editor-fold>

    //<editor-fold desc="All Set">
    public static function getAllSet(): bool
    {
        return (bool)self::get('iframely_all_set');
    }

    public static function setAllSet(bool $value): bool
    {
        return self::update('iframely_all_set', $value);
    }

    public static function isAllSet(): bool
    {
        return self::getAllSet();
    }
    //</editor-fold>

    //<editor-fold desc="Reactivation">
    public static function getReactivationFlag(): bool
    {
        return (bool)self::get('iframely_reactivation');
    }

    public static function setReactivationFlag(): bool
    {
        return self::update('iframely_reactivation', true);
    }

    public static function deleteReactivationFlag(): bool
    {
        return self::delete('iframely_reactivation');
    }
    //</editor-fold>

    //<editor-fold desc="API key: iframely_api_key">
    public static function getApiKey(): string
    {
        return (string)self::get('iframely_api_key');
    }

    public static function setApiKey(string $value = ''): bool
    {
        return self::update('iframely_api_key', $value);
    }
    //</editor-fold>

    //<editor-fold desc="Query string: iframely_api_params">
    public static function getApiParams(): string
    {
        return (string)self::get('iframely_api_params');
    }

    public static function setApiParams(string $value = ''): bool
    {
        return self::update('iframely_api_params', $value);
    }
    //</editor-fold>

    //<editor-fold desc="Cache refresh: iframely_cache_refresh">
    public static function getCacheRefresh(): bool
    {
        return (bool)self::get('iframely_cache_refresh');
    }

    public static function setCacheRefresh(bool $value): bool
    {
        return self::update('iframely_cache_refresh', $value);
    }

    public static function isCacheRefreshEnabled(): bool
    {
        return self::getCacheRefresh();
    }
    //</editor-fold>

    //<editor-fold desc="Cache interval: iframely_cache_interval">
    public static function getCacheTtl(): int
    {
        $value = (int)self::get('iframely_cache_ttl');
        return (int)apply_filters('iframely_cache_ttl', $value);
    }

    public static function setCacheTtl(int $value): bool
    {
        return self::update('iframely_cache_ttl', $value);
    }

    //</editor-fold>

    //<editor-fold desc="Replace default providers: iframely_builtins_replace">
    public static function getBuiltinsReplace(): bool
    {
        return (bool)self::get('iframely_builtins_replace');
    }

    public static function setBuiltinsReplace(bool $value): bool
    {
        return self::update('iframely_builtins_replace', $value);
    }

    public static function isBuiltinsReplaced(): bool
    {
        return self::getBuiltinsReplace();
    }
    //</editor-fold>

    //<editor-fold desc="Enhance previews: iframely_previews_enhance">
    public static function getPreviewsEnhance(): bool
    {
        return (bool)self::get('iframely_previews_enhance');
    }

    public static function setPreviewsEnhance(bool $value): bool
    {
        return self::update('iframely_previews_enhance', $value);
    }

    public static function isPreviewsEnhanced(): bool
    {
        return self::getPreviewsEnhance();
    }
    //</editor-fold>
}
