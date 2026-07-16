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

        session_start();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            error_log('[MiniShop3] SessionHelper: session_start() failed to activate PHP session');
        }
    }
}
