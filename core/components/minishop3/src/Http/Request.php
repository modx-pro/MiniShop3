<?php

namespace MiniShop3\Http;

/**
 * HTTP Request wrapper for Web API
 *
 * Encapsulates request data and provides convenient API to access it.
 * Not a full PSR-7 implementation, but follows the same principles.
 */
class Request
{
    /** @var array Request data (POST/GET parameters) */
    private array $data;

    /** @var array HTTP headers */
    private array $headers;

    /** @var array Route parameters from FastRoute */
    private array $routeParams;

    /** @var string HTTP method */
    private string $method;

    /** @var string Request URI */
    private string $uri;

    /**
     * @param array $data POST/GET data
     * @param array $headers HTTP headers
     * @param array $routeParams Route parameters
     * @param string $method HTTP method
     * @param string $uri Request URI
     */
    public function __construct(
        array $data = [],
        array $headers = [],
        array $routeParams = [],
        string $method = 'GET',
        string $uri = ''
    ) {
        $this->data = $data;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->routeParams = $routeParams;
        $this->method = strtoupper($method);
        $this->uri = $uri;
    }

    /**
     * Create Request from PHP global variables
     *
     * @return self
     */
    public static function createFromGlobals(): self
    {
        $data = $_REQUEST;

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return new self($data, $headers, [], $method, $uri);
    }

    /**
     * Get value from request data
     *
     * @param string $key Key
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get all request data
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get only specified keys from data
     *
     * @param array $keys Array of keys
     * @return array
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Get all data except specified keys
     *
     * @param array $keys Array of keys to exclude
     * @return array
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->data, array_flip($keys));
    }

    /**
     * Check if key exists in data
     *
     * @param string $key Key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Get header value
     *
     * @param string $key Header name (case-insensitive)
     * @param mixed $default Default value
     * @return mixed
     */
    public function header(string $key, $default = null)
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Get route parameter from FastRoute
     *
     * @param string $key Parameter name
     * @param mixed $default Default value
     * @return mixed
     */
    public function route(string $key, $default = null)
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Set route parameters (called from router)
     *
     * @param array $params Route parameters
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Get HTTP method
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get request URI
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Check if method is POST
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * Check if method is GET
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * Check if method is PUT
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }

    /**
     * Check if method is DELETE
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }
}
