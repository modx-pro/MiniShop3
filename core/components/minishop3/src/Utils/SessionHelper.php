<?php

namespace MiniShop3\Utils;

/**
 * PHP session bootstrap for MiniShop3 customer/cart flows.
 */
class SessionHelper
{
    public static function ensureActive(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            error_log('[MiniShop3] SessionHelper: PHP sessions are disabled');
            return;
        }

        session_start();
    }
}
