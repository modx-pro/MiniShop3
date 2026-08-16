<?php

namespace MiniShop3\Router;

/**
 * JSON Response class — HTTP boundary envelope for Manager/Web API.
 *
 * | Layer | Contract | Notes |
 * |-------|----------|-------|
 * | HTTP boundary (Api controllers, route handlers, middleware) | `Response::success` / `error` / `fromProcessor` | Always this shape on the wire via Router |
 * | Domain facades (Cart / Order / Customer) | MS2-array `{success,message,data}` | Snippet/plugin compatibility; do not re-wrap into Response inside domain |
 * | Legacy `modProcessor` | `failure` / `success` | Bridge at HTTP edge with `Response::fromProcessor` — never return raw `getResponse()` from mgr/web routes |
 *
 * Do not nest `Response` inside `$ms3->utils->success` (or the reverse) without a concrete need.
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/341
 */
class Response
{
    protected $data;
    protected $statusCode;
    protected $headers = [];

    /** @var string|null HTTP redirect target (Location) */
    protected ?string $redirectUrl = null;

    public function __construct($data, int $statusCode = HttpStatus::OK, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Redirect response (e.g. email verification in browser; api.php sends Location)
     */
    public static function redirect(string $url, int $statusCode = 302): Response
    {
        $r = new self(null, $statusCode);
        $r->redirectUrl = $url;

        return $r;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    /**
     * Create success response
     */
    public static function success(mixed $data = null, ?string $message = null, int $statusCode = HttpStatus::OK): Response
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Create error response.
     *
     * Envelope (#341 + #572):
     * `{ success:false, message, code, errors, error_code?, data? }`
     *
     * - `code` — HTTP status (int), same as response status
     * - `error_code` — optional stable snake_case machine key (additive)
     * - `errors` — field-level map only (string|string[]|MODX {msg}); not arbitrary payload
     * - `data` — optional non-field context (e.g. conflict existing_id, cart status)
     */
    public static function error(
        string $message,
        int $statusCode = HttpStatus::BAD_REQUEST,
        mixed $errors = null,
        ?string $errorCode = null,
        mixed $data = null,
    ): Response {
        $body = [
            'success' => false,
            'message' => $message,
            'code' => $statusCode,
            'errors' => $errors,
            'error_code' => self::resolveErrorCode($errorCode, $statusCode, $errors),
        ];
        if ($data !== null) {
            $body['data'] = $data;
        }

        return new self($body, $statusCode);
    }

    /**
     * Error with required machine `error_code` (Web API / Nuxt).
     */
    public static function errorWithCode(
        string $errorCode,
        string $message,
        int $statusCode = HttpStatus::BAD_REQUEST,
        mixed $errors = null,
        mixed $data = null,
    ): Response {
        return self::error($message, $statusCode, $errors, $errorCode, $data);
    }

    /**
     * Map a MODX processor response to an API Response.
     *
     * Do not return processor `getResponse()` from routes: connector Index unwraps
     * `Response` as `$responseData['data'] ?? $responseData`, so a raw processor
     * payload nests as `object.object.*`.
     *
     * Error shape:
     * - `errors` — MODX field/validation map from `addFieldError` (when present)
     * - `data` — processor object minus transport-only `code` (import stats, etc.)
     * - HTTP/`code` — from object `code` when it is an allowed HttpStatus
     *   (Login/Register rate-limit and auth failures)
     *
     * @param object $processorResponse modProcessorResponse (isError/getMessage/getObject)
     */
    public static function fromProcessor(object $processorResponse): Response
    {
        if (!$processorResponse->isError()) {
            return self::success($processorResponse->getObject(), $processorResponse->getMessage());
        }

        $object = $processorResponse->getObject();
        $status = self::statusFromProcessorObject($object);
        $fieldErrors = self::fieldErrorsFromProcessor($processorResponse);
        $message = (string) $processorResponse->getMessage();
        if ($message === '' && $fieldErrors !== null) {
            $message = self::messageFromFieldErrors($fieldErrors);
        }

        return self::error(
            $message,
            $status,
            $fieldErrors,
            $fieldErrors !== null ? ApiErrorCode::VALIDATION_FAILED : null,
            self::dataFromProcessorObject($object),
        );
    }

    /**
     * Processor failure object minus transport-only `code`.
     */
    private static function dataFromProcessorObject(mixed $object): mixed
    {
        if (!is_array($object)) {
            return null;
        }

        $data = $object;
        unset($data['code']);

        return $data !== [] ? $data : null;
    }

    private static function resolveErrorCode(?string $errorCode, int $statusCode, mixed $errors = null): string
    {
        if ($errorCode !== null && $errorCode !== '') {
            return $errorCode;
        }
        if (is_array($errors) && $errors !== []) {
            return ApiErrorCode::VALIDATION_FAILED;
        }

        return ApiErrorCode::fromHttpStatus($statusCode);
    }

    /**
     * Field errors from modProcessorResponse (addFieldError → getResponse()['errors']).
     *
     * @return array<int|string, mixed>|null
     */
    public static function fieldErrorsFromProcessor(object $processorResponse): ?array
    {
        if (method_exists($processorResponse, 'getResponse')) {
            $raw = $processorResponse->getResponse();
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw) && !empty($raw['errors']) && is_array($raw['errors'])) {
                return $raw['errors'];
            }
        }

        if (
            method_exists($processorResponse, 'hasFieldErrors')
            && method_exists($processorResponse, 'getFieldErrors')
            && $processorResponse->hasFieldErrors()
        ) {
            $fieldErrors = $processorResponse->getFieldErrors();
            if (is_array($fieldErrors) && $fieldErrors !== []) {
                return $fieldErrors;
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $fieldErrors
     */
    private static function messageFromFieldErrors(array $fieldErrors): string
    {
        $first = reset($fieldErrors);
        if (is_array($first) && isset($first['msg'])) {
            return (string) $first['msg'];
        }
        if (is_object($first) && isset($first->msg)) {
            return (string) $first->msg;
        }
        if (is_string($first) && $first !== '') {
            return $first;
        }

        return '';
    }

    /**
     * Resolve HTTP status from a processor failure object.
     *
     * @param mixed $object Value from modProcessorResponse::getObject()
     */
    public static function statusFromProcessorObject(
        mixed $object,
        int $default = HttpStatus::BAD_REQUEST
    ): int {
        if (is_string($object) && $object !== '') {
            $decoded = json_decode($object, true);
            if (is_array($decoded)) {
                $object = $decoded;
            }
        }

        if (!is_array($object) || !isset($object['code']) || !is_numeric($object['code'])) {
            return $default;
        }

        $code = (int) $object['code'];

        return self::isAllowedErrorStatus($code) ? $code : $default;
    }

    private static function isAllowedErrorStatus(int $code): bool
    {
        return in_array($code, [
            HttpStatus::BAD_REQUEST,
            HttpStatus::UNAUTHORIZED,
            HttpStatus::FORBIDDEN,
            HttpStatus::NOT_FOUND,
            HttpStatus::CONFLICT,
            HttpStatus::UNPROCESSABLE_ENTITY,
            HttpStatus::TOO_MANY_REQUESTS,
            HttpStatus::INTERNAL_SERVER_ERROR,
            HttpStatus::SERVICE_UNAVAILABLE,
        ], true);
    }

    /**
     * Set header
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send response to client
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        if ($this->redirectUrl !== null) {
            header('Location: ' . $this->redirectUrl);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Get response data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
