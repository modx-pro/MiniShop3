<?php

namespace MiniShop3\Http;

use MODX\Revolution\modX;

/**
 * HTTP Response helper for Web API
 *
 * Forms JSON responses with proper HTTP status codes.
 * Replaces old Utils::success() and Utils::error() methods.
 */
class Response
{
    /** @var modX */
    private modX $modx;

    /**
     * HTTP status codes
     */
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * @param modX $modx MODX instance for lexicon access
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Send JSON response with specified status code
     *
     * @param array $data Data for JSON
     * @param int $status HTTP status code
     * @return void
     */
    public function json(array $data, int $status = self::HTTP_OK): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Success response
     *
     * @param string $message Lexicon key or message text
     * @param array $data Additional data
     * @param array $placeholders Placeholders for lexicon
     * @param int $status HTTP status code (default 200)
     * @return void
     */
    public function success(
        string $message = '',
        array $data = [],
        array $placeholders = [],
        int $status = self::HTTP_OK
    ): void {
        $this->json([
            'success' => true,
            'message' => $this->translate($message, $placeholders),
            'data' => $data,
        ], $status);
    }

    /**
     * Resource created response
     *
     * @param string $message Lexicon key or message text
     * @param array $data Created resource data
     * @param array $placeholders Placeholders for lexicon
     * @return void
     */
    public function created(
        string $message = '',
        array $data = [],
        array $placeholders = []
    ): void {
        $this->success($message, $data, $placeholders, self::HTTP_CREATED);
    }

    /**
     * No content response (e.g., after successful deletion)
     *
     * @return void
     */
    public function noContent(): void
    {
        http_response_code(self::HTTP_NO_CONTENT);
        exit;
    }

    /**
     * Error response
     *
     * @param string $message Lexicon key or message text
     * @param array $errors Error details (for validation)
     * @param array $placeholders Placeholders for lexicon
     * @param int $status HTTP status code (default 400)
     * @return void
     */
    public function error(
        string $message = '',
        array $errors = [],
        array $placeholders = [],
        int $status = self::HTTP_BAD_REQUEST
    ): void {
        $response = [
            'success' => false,
            'message' => $this->translate($message, $placeholders),
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $status);
    }

    /**
     * 400 Bad Request response
     *
     * @param string $message Error message
     * @param array $errors Error details
     * @param array $placeholders Placeholders for lexicon
     * @return void
     */
    public function badRequest(
        string $message,
        array $errors = [],
        array $placeholders = []
    ): void {
        $this->error($message, $errors, $placeholders, self::HTTP_BAD_REQUEST);
    }

    /**
     * 401 Unauthorized response
     *
     * @param string $message Error message (default "ms3_err_token")
     * @return void
     */
    public function unauthorized(string $message = 'ms3_err_token'): void
    {
        $this->error($message, [], [], self::HTTP_UNAUTHORIZED);
    }

    /**
     * 403 Forbidden response
     *
     * @param string $message Error message
     * @return void
     */
    public function forbidden(string $message = 'ms3_err_permission_denied'): void
    {
        $this->error($message, [], [], self::HTTP_FORBIDDEN);
    }

    /**
     * 404 Not Found response
     *
     * @param string $message Error message
     * @param array $placeholders Placeholders for lexicon
     * @return void
     */
    public function notFound(string $message, array $placeholders = []): void
    {
        $this->error($message, [], $placeholders, self::HTTP_NOT_FOUND);
    }

    /**
     * 422 Unprocessable Entity response (validation errors)
     *
     * @param string $message General error message
     * @param array $errors Validation error details
     * @return void
     */
    public function validationError(string $message, array $errors = []): void
    {
        $this->error($message, $errors, [], self::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * 429 Too Many Requests response
     *
     * @param string $message Error message
     * @param int $retryAfter Seconds after which request can be retried
     * @return void
     */
    public function tooManyRequests(string $message = 'ms3_err_rate_limit', int $retryAfter = 60): void
    {
        header("Retry-After: $retryAfter");
        $this->error($message, [], [], self::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * 500 Internal Server Error response
     *
     * @param string $message Error message
     * @param array $details Error details (only in dev mode)
     * @return void
     */
    public function serverError(string $message = 'ms3_err_unknown', array $details = []): void
    {
        $response = [
            'success' => false,
            'message' => $this->translate($message),
        ];

        // Add error details in dev mode
        if (!empty($details) && $this->modx->getOption('debug', null, false)) {
            $response['details'] = $details;
        }

        $this->json($response, self::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Translate lexicon key or return text as is
     *
     * @param string $message Lexicon key or text
     * @param array $placeholders Placeholders for lexicon
     * @return string
     */
    private function translate(string $message, array $placeholders = []): string
    {
        if (empty($message)) {
            return '';
        }

        // Try to translate as lexicon key
        $translated = $this->modx->lexicon($message, $placeholders);

        // If key not found, lexicon() will return the key itself
        // In this case return original message
        return $translated;
    }
}
