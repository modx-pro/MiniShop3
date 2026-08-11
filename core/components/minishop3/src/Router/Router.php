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

    /** @var string */
    protected $currentPrefix = '';

    /** @var array */
    protected $currentMiddlewares = [];

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Absolute path: core/config/ms3.routes.d/manager or .../web
     *
     * @param 'manager'|'web' $segment
     */
    public static function coreAddonRoutesDirectory(string $segment): string
    {
        if ($segment !== 'manager' && $segment !== 'web') {
            throw new \InvalidArgumentException(
                "coreAddonRoutesDirectory: segment must be 'manager' or 'web', got: {$segment}"
            );
        }

        return MODX_CORE_PATH . 'config/ms3.routes.d/' . $segment;
    }

    /**
     * Storefront API routes served by api.php — not via manager connector (#384).
     */
    public static function isStorefrontRoute(string $route): bool
    {
        return str_starts_with($route, '/api/v1');
    }

    /**
     * Planned manager-route sources (connector Index/Router). Never includes web (#384).
     *
     * @return list<array{kind: 'file'|'dir', path: string, required: bool}>
     */
    public static function managerRoutePlan(string $componentPath, string $corePath): array
    {
        $componentPath = rtrim($componentPath, '/\\') . DIRECTORY_SEPARATOR;
        $corePath = rtrim($corePath, '/\\') . DIRECTORY_SEPARATOR;

        return [
            [
                'kind' => 'file',
                'path' => $componentPath . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'manager.php',
                'required' => true,
            ],
            [
                'kind' => 'file',
                'path' => $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3_routes_manager.custom.php',
                'required' => false,
            ],
            [
                'kind' => 'dir',
                'path' => $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'manager',
                'required' => false,
            ],
        ];
    }

    /**
     * Planned web-route sources (api.php). Never includes manager (#384).
     *
     * @return list<array{kind: 'file'|'dir', path: string, required: bool}>
     */
    public static function webRoutePlan(string $componentPath, string $corePath): array
    {
        $componentPath = rtrim($componentPath, '/\\') . DIRECTORY_SEPARATOR;
        $corePath = rtrim($corePath, '/\\') . DIRECTORY_SEPARATOR;

        return [
            [
                'kind' => 'file',
                'path' => $componentPath . 'config' . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php',
                'required' => true,
            ],
            [
                'kind' => 'file',
                'path' => $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3_routes_web.custom.php',
                'required' => false,
            ],
            [
                'kind' => 'dir',
                'path' => $corePath . 'config' . DIRECTORY_SEPARATOR . 'ms3.routes.d' . DIRECTORY_SEPARATOR . 'web',
                'required' => false,
            ],
        ];
    }

    /**
     * Load manager-only routes for connector.php processors.
     *
     * @throws \RuntimeException when required manager.php is missing
     */
    public function loadManagerRoutes(string $componentPath, string $corePath): self
    {
        return $this->loadRoutePlan(self::managerRoutePlan($componentPath, $corePath));
    }

    /**
     * Load storefront routes for api.php.
     *
     * @throws \RuntimeException when required web.php is missing
     */
    public function loadWebRoutes(string $componentPath, string $corePath): self
    {
        return $this->loadRoutePlan(self::webRoutePlan($componentPath, $corePath));
    }

    /**
     * @param list<array{kind: 'file'|'dir', path: string, required: bool}> $plan
     */
    protected function loadRoutePlan(array $plan): self
    {
        foreach ($plan as $item) {
            if ($item['kind'] === 'dir') {
                $this->loadRoutesFromDirectory($item['path']);
                continue;
            }

            if (!file_exists($item['path'])) {
                if (!empty($item['required'])) {
                    throw new \RuntimeException('System routes not found: ' . $item['path']);
                }
                continue;
            }

            $this->loadRoutes($item['path']);
        }

        return $this;
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

        $this->requireRoutesFile($routesFile);

        return $this;
    }

    /**
     * Load route fragments from a directory (*.php, sorted alphabetically).
     *
     * Missing directory is ignored. A broken file is logged; other files still load.
     *
     * @param string $dir Absolute path to directory (with or without trailing slash)
     * @return self
     */
    public function loadRoutesFromDirectory(string $dir): self
    {
        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;

        if (!is_dir($dir)) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[MiniShop3 Router] Routes directory not found: {$dir}"
            );

            return $this;
        }

        $files = glob($dir . '*.php');
        if ($files === false || $files === []) {
            return $this;
        }

        sort($files, SORT_STRING);

        foreach ($files as $file) {
            try {
                $this->requireRoutesFile($file);
            } catch (\Throwable $e) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        '[MiniShop3 Router] Failed to load routes file %s: %s',
                        $file,
                        $e->getMessage()
                    )
                );
            }
        }

        return $this;
    }

    /**
     * @param string $routesFile Must exist and be readable
     */
    protected function requireRoutesFile(string $routesFile): void
    {
        $modx = $this->modx;
        $router = $this;

        require $routesFile;
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
     * Routes are indexed by method:pattern key, allowing custom routes
     * to override system routes with the same method and pattern.
     *
     * @param string|array $method HTTP method (GET, POST, PUT, DELETE) or array of methods
     * @param string $pattern URL pattern
     * @param callable|string $handler Handler (closure or string 'Controller@method')
     * @param array $middlewares Middleware array
     * @return Route
     */
    public function addRoute($method, string $pattern, $handler, array $middlewares = []): Route
    {
        $fullPattern = $this->currentPrefix . $pattern;

        $allMiddlewares = array_merge($this->currentMiddlewares, $middlewares);

        $route = new Route($method, $fullPattern, $handler, $allMiddlewares);

        // Generate unique key for route override detection
        // Normalize method(s) to uppercase and sort for consistent key
        $methods = is_array($method) ? $method : [$method];
        sort($methods);
        $methodKey = implode('|', array_map('strtoupper', $methods));
        $routeKey = $methodKey . ':' . $fullPattern;

        // Store with key - allows custom routes to override system routes
        $this->routes[$routeKey] = $route->toArray();

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
        $previousPrefix = $this->currentPrefix;
        $previousMiddlewares = $this->currentMiddlewares;

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
                return Response::error('Route not found', HttpStatus::NOT_FOUND);

            case Dispatcher::METHOD_NOT_ALLOWED:
                return Response::error('Method not allowed', HttpStatus::METHOD_NOT_ALLOWED);

            case Dispatcher::FOUND:
                $routeData = $routeInfo[1];
                $vars = $routeInfo[2];

                return $this->executeRoute($routeData, $vars);
        }

        return Response::error('Unknown error', HttpStatus::INTERNAL_SERVER_ERROR);
    }

    /**
     * Execute route handler with middleware
     *
     * @param array $routeData
     * @param array $vars URL parameters from route pattern
     * @return Response
     */
    protected function executeRoute(array $routeData, array $vars): Response
    {
        $handler = $routeData['handler'];
        $middlewares = $routeData['middlewares'] ?? [];

        // Merge URL pattern vars with query string parameters
        // URL pattern vars take precedence over query params
        $vars = array_merge($_GET, $vars);

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
                return Response::error("Controller not found: {$controllerClass}", HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $controller = new $controllerClass($this->modx);

            if (!method_exists($controller, $method)) {
                return Response::error("Method not found: {$method}", HttpStatus::INTERNAL_SERVER_ERROR);
            }

            $result = $controller->$method($vars);
            return $this->normalizeResponse($result);
        }

        return Response::error('Invalid handler', HttpStatus::INTERNAL_SERVER_ERROR);
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

        return Response::error('Invalid response type', HttpStatus::INTERNAL_SERVER_ERROR);
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
