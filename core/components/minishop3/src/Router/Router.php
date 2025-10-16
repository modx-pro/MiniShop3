<?php

namespace MiniShop3\Router;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use MODX\Revolution\modX;
use function FastRoute\simpleDispatcher;

/**
 * API Router для MiniShop3
 *
 * Использует FastRoute для маршрутизации API запросов
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
     * Загрузить роуты из файла конфигурации
     *
     * @param string $routesFile
     * @return self
     */
    public function loadRoutes(string $routesFile): self
    {
        if (!file_exists($routesFile)) {
            throw new \RuntimeException("Routes file not found: {$routesFile}");
        }

        $modx = $this->modx; // Для доступа в файле routes
        $router = $this;

        require $routesFile;

        return $this;
    }

    /**
     * Создать dispatcher из зарегистрированных роутов
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
     * Добавить роут
     *
     * @param string|array $method HTTP метод (GET, POST, PUT, DELETE) или массив методов
     * @param string $pattern URL паттерн
     * @param callable|string $handler Обработчик (замыкание или строка 'Controller@method')
     * @param array $middlewares Массив middleware
     * @return Route
     */
    public function addRoute($method, string $pattern, $handler, array $middlewares = []): Route
    {
        // Применяем префикс группы к паттерну
        $fullPattern = ($this->currentPrefix ?? '') . $pattern;

        // Объединяем middleware группы с middleware роута
        $allMiddlewares = array_merge($this->currentMiddlewares ?? [], $middlewares);

        $route = new Route($method, $fullPattern, $handler, $allMiddlewares);
        $this->routes[] = $route->toArray();

        return $route;
    }

    /**
     * GET роут
     */
    public function get(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('GET', $pattern, $handler, $middlewares);
    }

    /**
     * POST роут
     */
    public function post(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('POST', $pattern, $handler, $middlewares);
    }

    /**
     * PUT роут
     */
    public function put(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('PUT', $pattern, $handler, $middlewares);
    }

    /**
     * DELETE роут
     */
    public function delete(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middlewares);
    }

    /**
     * PATCH роут
     */
    public function patch(string $pattern, $handler, array $middlewares = []): Route
    {
        return $this->addRoute('PATCH', $pattern, $handler, $middlewares);
    }

    /**
     * Группа роутов с общим префиксом и middleware
     *
     * @param string $prefix Префикс для всех роутов группы
     * @param callable $callback Замыкание с определением роутов
     * @param array $middlewares Middleware для всей группы
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
     * Обработать текущий HTTP запрос
     *
     * @param string|null $uri Опциональный URI (если null - берется из $_SERVER['REQUEST_URI'])
     * @param string|null $method Опциональный HTTP метод (если null - берется из $_SERVER['REQUEST_METHOD'])
     * @return Response
     */
    public function dispatch(?string $uri = null, ?string $method = null): Response
    {
        $httpMethod = $method ?? $_SERVER['REQUEST_METHOD'];
        $uri = $uri ?? $_SERVER['REQUEST_URI'];

        // Убрать query string
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
     * Выполнить обработчик роута с middleware
     *
     * @param array $routeData
     * @param array $vars URL параметры
     * @return Response
     */
    protected function executeRoute(array $routeData, array $vars): Response
    {
        $handler = $routeData['handler'];
        $middlewares = $routeData['middlewares'] ?? [];

        // Выполнить middleware
        foreach ($middlewares as $middleware) {
            $middlewareInstance = $this->resolveMiddleware($middleware);
            $result = $middlewareInstance->handle($vars);

            if ($result instanceof Response) {
                return $result; // Middleware прервал выполнение
            }
        }

        // Выполнить обработчик
        if (is_callable($handler)) {
            return $handler($vars, $this->modx);
        }

        // Формат: 'Controller@method'
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerClass, $method] = explode('@', $handler);

            if (!class_exists($controllerClass)) {
                return Response::error("Controller not found: {$controllerClass}", 500);
            }

            $controller = new $controllerClass($this->modx);

            if (!method_exists($controller, $method)) {
                return Response::error("Method not found: {$method}", 500);
            }

            return $controller->$method($vars);
        }

        return Response::error('Invalid handler', 500);
    }

    /**
     * Создать экземпляр middleware
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
