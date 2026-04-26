<?php

namespace MiniShop3\Router;

/**
 * HTTP status codes used by the MiniShop3 API layer.
 *
 * Single source of truth — only codes actually emitted by the codebase are listed.
 * Add a constant when a new code is introduced; do not pre-populate "common" codes
 * that no handler returns.
 */
final class HttpStatus
{
    public const OK = 200;
    public const CREATED = 201;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const UNPROCESSABLE_ENTITY = 422;
    public const TOO_MANY_REQUESTS = 429;
    public const INTERNAL_SERVER_ERROR = 500;
    public const SERVICE_UNAVAILABLE = 503;
}
