<?php

namespace Iframely\UI;

class Links
{
    public const UTM_SOURCE = 'wordpress-plugin';

    public static function link(string $path = ''): string
    {
        $base = 'https://iframely.com';
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }
        $url = implode('/', [$base, $path]);
        return $url . '?utm_source=' . self::UTM_SOURCE;
    }

    public static function settings(): string
    {
        return is_network_admin() ? network_admin_url('settings.php?page=iframely') : menu_page_url('iframely', false);
    }

    public static function activation(): string
    {
        return is_network_admin() ? network_admin_url('settings.php?page=iframely') : menu_page_url('iframely', false);
    }

    public static function tab(string $name = '', string $action = ''): string
    {
        $url = self::settings();
        $data = [];
        if (!empty($name)) {
            $data['tab'] = $name;
        }
        if (!empty($action)) {
            $data['action'] = $action;
        }
        if (!empty($data)) {
            return $url . '&' . http_build_query($data);
        }
        return $url;
    }
}
