<?php

declare(strict_types=1);

namespace MiniShop3\Router;

/**
 * Maps domain MS2-array `{success,message,data}` failures to Web API Response (#572).
 */
final class DomainMs2Response
{
    /**
     * @param array<string, mixed> $result
     */
    public static function fromDomain(array $result, string $fallbackMessage): Response
    {
        if (!empty($result['success'])) {
            return Response::success($result['data'] ?? null, $result['message'] ?? '');
        }

        return self::failure(
            (string) ($result['message'] ?? $fallbackMessage),
            $result['data'] ?? null,
        );
    }

    /**
     * @return Response
     */
    public static function failure(string $message, mixed $data = null): Response
    {
        $fieldErrors = null;
        $context = $data;

        if (is_array($data) && self::isFieldErrorMap($data['errors'] ?? null)) {
            /** @var array<string, mixed> $fieldErrors */
            $fieldErrors = $data['errors'];
            $context = $data;
            unset($context['errors']);
            if ($context === []) {
                $context = null;
            }
        }

        if ($fieldErrors !== null) {
            return Response::errorWithCode(
                ApiErrorCode::VALIDATION_FAILED,
                $message,
                HttpStatus::UNPROCESSABLE_ENTITY,
                $fieldErrors,
                $context,
            );
        }

        if (self::looksLikeTokenError($message)) {
            return Response::errorWithCode(
                ApiErrorCode::TOKEN_REQUIRED,
                $message,
                HttpStatus::UNAUTHORIZED,
                null,
                $context,
            );
        }

        if (self::looksLikeNotFound($message)) {
            return Response::errorWithCode(
                ApiErrorCode::NOT_FOUND,
                $message,
                HttpStatus::NOT_FOUND,
                null,
                $context,
            );
        }

        return Response::errorWithCode(
            ApiErrorCode::BUSINESS_RULE,
            $message,
            HttpStatus::BAD_REQUEST,
            null,
            $context,
        );
    }

    private static function isFieldErrorMap(mixed $errors): bool
    {
        if (!is_array($errors) || $errors === []) {
            return false;
        }

        // MODX processor list: [{id, msg}, ...] — not a field map
        if (array_is_list($errors)) {
            $first = $errors[0] ?? null;
            if (is_array($first) && (isset($first['id']) || isset($first['msg']))) {
                return false;
            }
        }

        foreach ($errors as $value) {
            if (!is_string($value) && !is_array($value) && !is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    private static function looksLikeTokenError(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'ms3_err_token')
            || str_contains($normalized, 'ms3_customer_err_token');
    }

    private static function looksLikeNotFound(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, '_nf')
            || str_contains($normalized, 'not found')
            || str_contains($normalized, 'не найден');
    }
}
