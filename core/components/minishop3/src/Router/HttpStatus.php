<?php

namespace MiniShop3\Router;

/**
 * HTTP status codes used by the MiniShop3 API layer.
 *
 * Single source of truth; {@see Response} re-exports the same values as HTTP_* for convenience.
 */
final class HttpStatus
{
    public const OK = 200;
    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const INTERNAL_SERVER_ERROR = 500;
}
