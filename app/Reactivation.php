<?php

namespace Iframely;

class Reactivation
{
    public static function start(bool $state = true): void
    {
        Options::setReactivationFlag();
    }

    public static function end(bool $state = true): void
    {
        Options::deleteReactivationFlag();
    }

    public static function isTab(): bool
    {
        return isset($_GET['action']) && ($_GET['action'] === 'reactivate');
    }

    public static function isRequest(): bool
    {
        return isset($_POST['iframely_reactivation_request']);
    }

    public static function inProgress(): bool
    {
        return isset($_POST['iframely_reactivation']) && Options::getReactivationFlag();
    }
}
