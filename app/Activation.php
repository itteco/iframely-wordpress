<?php

namespace Iframely;

use Iframely\Embed\Cache;

class Activation
{
    public static function run(): void
    {
        if (Plugin::isActivated() || Options::isAllSet()) {
            return;
        }

        Options::setBuiltinsReplace(true);
        Options::setPreviewsEnhance(true);
        Options::setCacheRefresh(true);
        Options::setCacheTtl(Cache::getDefaultTtlValue());

        Options::setAllSet(true);
    }
}
