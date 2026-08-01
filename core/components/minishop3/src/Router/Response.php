<?php

namespace MiniShop3\Router;

/**
 * JSON Response class
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
    public static function redirect(string $url, int $statusCode = 302): self
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
    public static function success(mixed $data = null, ?string $message = null, int $statusCode = HttpStatus::OK): self
    {
        return new self([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Create error response
     */
    public static function error(string $message, int $statusCode = HttpStatus::BAD_REQUEST, mixed $errors = null): self
    {
        return new self([
            'success' => false,
            'message' => $message,
            'code' => $statusCode,
            'errors' => $errors
        ], $statusCode);
    }

    /**
     * Map a MODX processor response to an API Response.
     *
     * Error processors may pass ['code' => HttpStatus::…] as the failure object
     * (Login/Register rate-limit and auth failures).
     *
     * @param object $processorResponse modProcessorResponse (isError/getMessage/getObject)
     */
    public static function fromProcessor(object $processorResponse): self
    {
        if (!$processorResponse->isError()) {
            return self::success($processorResponse->getObject(), $processorResponse->getMessage());
        }

        return self::error(
            (string) $processorResponse->getMessage(),
            self::statusFromProcessorObject($processorResponse->getObject())
        );
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
