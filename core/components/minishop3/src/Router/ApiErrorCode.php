<?php

declare(strict_types=1);

namespace MiniShop3\Router;

/**
 * Stable machine-readable error_code values for Web API (#572).
 *
 * Additive on error envelope; clients may ignore unknown codes.
 */
final class ApiErrorCode
{
    public const VALIDATION_FAILED = 'validation_failed';
    public const UNAUTHORIZED = 'unauthorized';
    public const TOKEN_REQUIRED = 'token_required';
    public const TOKEN_EXPIRED = 'token_expired';
    public const TOKEN_INVALID = 'token_invalid';
    public const NOT_FOUND = 'not_found';
    public const CONFLICT = 'conflict';
    public const RATE_LIMITED = 'rate_limited';
    public const BUSINESS_RULE = 'business_rule';
    public const BAD_REQUEST = 'bad_request';
    public const FORBIDDEN = 'forbidden';
    public const INTERNAL_ERROR = 'internal_error';

    public static function fromHttpStatus(int $statusCode): string
    {
        return match ($statusCode) {
            HttpStatus::UNAUTHORIZED => self::UNAUTHORIZED,
            HttpStatus::FORBIDDEN => self::FORBIDDEN,
            HttpStatus::NOT_FOUND => self::NOT_FOUND,
            HttpStatus::CONFLICT => self::CONFLICT,
            HttpStatus::TOO_MANY_REQUESTS => self::RATE_LIMITED,
            HttpStatus::UNPROCESSABLE_ENTITY => self::VALIDATION_FAILED,
            HttpStatus::INTERNAL_SERVER_ERROR,
            HttpStatus::SERVICE_UNAVAILABLE => self::INTERNAL_ERROR,
            HttpStatus::BAD_REQUEST => self::BAD_REQUEST,
            default => self::BAD_REQUEST,
        };
    }
}
