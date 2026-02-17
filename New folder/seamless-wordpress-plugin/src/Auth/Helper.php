<?php

namespace Seamless\Auth;

/**
 * Helper class for common functions like logging and session management.
 */
class Helper
{
    public static function log(string $message): void
    {
        error_log(SeamlessSSO::SSO_PREFIX . ': ' . $message);
    }
}
