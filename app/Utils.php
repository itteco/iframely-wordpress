<?php

namespace Iframely;

class Utils
{
    public static function stringContains(string $haystack = '', string $needle = ''): bool
    {
        if (function_exists('str_contains')) {
            return str_contains($haystack, $needle);
        }
        return strpos($haystack, $needle) !== false;
    }
}
