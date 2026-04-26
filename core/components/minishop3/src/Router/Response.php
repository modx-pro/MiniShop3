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

    public function __construct($data, int $statusCode = HttpStatus::OK, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
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
