<?php

namespace MiniShop3\Router;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use MODX\Revolution\modX;
use function FastRoute\simpleDispatcher;

/**
 * API Router for MiniShop3
 *
 * Uses FastRoute for API request routing
 */
class Router
{
    /** @var modX */
    protected $modx;

    /** @var Dispatcher */
    protected $dispatcher;

    /** @var array */
    protected $routes = [];

    /** @var array */
    protected $middlewares = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Load routes from configuration file
     *
     * @param string $routesFile
     * @return self
     */
    public function loadRoutes(string $routesFile): self
    {
        if (!file_exists($routesFile)) {
            throw new \RuntimeException("Routes file not found: {$routesFile}");
        }

        $modx = $this->modx;
        $router = $this;

        require $routesFile;

        return $this;
    }

    /**
     * Build dispatcher from registered routes
     *
     * @return self
     */
    public function build(): self
    {
        $this->dispatcher = simpleDispatcher(function(RouteCollector $r) {
            foreach ($this->routes as $route) {
                $r->addRoute(
                    $route['method'],
                    $route['pattern'],
                    [
                        'handler' => $route['handler'],
                        'middlewares' => $route['middlewares'] ?? []
                    ]
                );
            }
        });

        return $this;
    }

    /**
     * Add route
     *
     * @param string|array $method HTTP method (GET, POST, PUT, DELETE) or array of methods
     * @param string $pattern URL pattern
     * @param callable|string $handler Handler (closure or string 'Controller@method')
     * @param array $middlewares Middleware array
     * @return Route
     */
    public function addRoute($method, string $pattern, $handler, array $middlewares = []): Route
    {
        $fullPattern = ($this->currentPrefix ?? '') . $pattern;

        $allMiddlewares = array_merge($this->currentMiddlewares ?? [], $middlewares);

        $route = new Route($method, $fullPattern, $handler, $allMiddlewares);
        $this->routes[] = $route->toArray();

        return $route;
    }

    /**
     * GET route
     */
    public function get(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('GET', $pattern, $handler, $middlewares);
    }

    /**
     * POST route
     */
    public function post(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('POST', $pattern, $handler, $middlewares);
    }

    /**
     * PUT route
     */
    public function put(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }

    /**
     * DELETE route
     */
    public function delete(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }

    /**
     * PATCH route
     */
    public function patch(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler, $middlewares);
    }

    /**
     * Route group with common prefix and middleware
     *
     * @param string $prefix Prefix for all routes in group
     * @param callable $callback Closure with route definitions
     * @param array $middlewares Middleware for the entire group
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $previousPrefix = $this->currentPrefix ?? '';
        $previousMiddlewares = $this->currentMiddlewares ?? [];

        $this->currentPrefix = $previousPrefix . $prefix;
        $this->currentMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentMiddlewares = $previousMiddlewares;
    }

    /**
     * Dispatch current HTTP request
     *
     * @param string|null $uri Optional URI (if null - taken from $_SERVER['REQUEST_URI'])
     * @param string|null $method Optional HTTP method (if null - taken from $_SERVER['REQUEST_METHOD'])
     * @return Response
     */
    public function dispatch(?string $uri = null, ?string $method = null): Response
    {
        $httpMethod = $method ?? $_SERVER['REQUEST_METHOD'];
        $uri = $uri ?? $_SERVER['REQUEST_URI'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                return Response::error('Route not found', 404);

            case Dispatcher::METHOD_NOT_ALLOWED:
                return Response::error('Method not allowed', 405);

            case Dispatcher::FOUND:
                $routeData = $routeInfo[1];
                $vars = $routeInfo[2];

                return $this->executeRoute($routeData, $vars);
        }

        return Response::error('Unknown error', 500);
    }

    /**
     * Execute route handler with middleware
     *
     * @param array $routeData
     * @param array $vars URL parameters
     * @return Response
     */
    protected function executeRoute(array $routeData, array $vars): Response
    {
        $handler = $routeData['handler'];
        $middlewares = $routeData['middlewares'] ?? [];

        foreach ($middlewares as $middleware) {
            $middlewareInstance = $this->resolveMiddleware($middleware);
            $result = $middlewareInstance->handle($vars);

            if ($result instanceof Response) {
                return $result;
            }
        }

        if (is_callable($handler)) {
            $result = $handler($vars, $this->modx);
            return $this->normalizeResponse($result);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerClass, $method] = explode('@', $handler);

            if (!class_exists($controllerClass)) {
                return Response::error("Controller not found: {$controllerClass}", 500);
            }

            $controller = new $controllerClass($this->modx);

            if (!method_exists($controller, $method)) {
                return Response::error("Method not found: {$method}", 500);
            }

            $result = $controller->$method($vars);
            return $this->normalizeResponse($result);
        }

        return Response::error('Invalid handler', 500);
    }

    /**
     * Normalize handler response
     * If handler returns array, wrap it in Response
     *
     * @param mixed $result Handler execution result
     * @return Response
     */
    protected function normalizeResponse($result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            $statusCode = 200;
            if (isset($result['success']) && !$result['success']) {
                $statusCode = $result['code'] ?? 400;
            }

            return new Response($result, $statusCode);
        }

        return Response::error('Invalid response type', 500);
    }

    /**
     * Create middleware instance
     *
     * @param string|object $middleware
     * @return Middleware\MiddlewareInterface
     */
    protected function resolveMiddleware($middleware)
    {
        if (is_object($middleware)) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            return new $middleware($this->modx);
        }

        throw new \RuntimeException("Cannot resolve middleware: {$middleware}");
    }
}
