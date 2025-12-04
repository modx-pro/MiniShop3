<?php

namespace MiniShop3\Router;

/**
 * Route class
 */
class Route
{
    protected $method;
    protected $pattern;
    protected $handler;
    protected $middlewares = [];
    protected $name;

    public function __construct($method, string $pattern, $handler, array $middlewares = [])
    {
        $this->method = $method;
        $this->pattern = $pattern;
        $this->handler = $handler;
        $this->middlewares = $middlewares;
    }

    /**
     * Add middleware to route
     */
    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->middlewares = array_merge($this->middlewares, $middleware);
        } else {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    /**
     * Set route name
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get route data array
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'pattern' => $this->pattern,
            'handler' => $this->handler,
            'middlewares' => $this->middlewares,
            'name' => $this->name
        ];
    }
}
