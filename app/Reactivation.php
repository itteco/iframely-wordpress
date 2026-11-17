<?php

namespace Iframely;

class Reactivation
{
    public static function start(): void
    {
        Options::setReactivationFlag();
    }

    public static function end(): void
    {
        Options::deleteReactivationFlag();
    }

    # The three checks below only tell which page or form this is. Nothing is changed on their
    # word: Settings::save() verifies the nonce of a form before it acts on it

    public static function isTab(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above
        return isset($_GET['action']) && ($_GET['action'] === 'reactivate');
    }

    public static function isRequest(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above
        return isset($_POST['iframely_reactivation_request']);
    }

    public static function inProgress(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above
        return isset($_POST['iframely_reactivation']) && Options::getReactivationFlag();
    }
}
