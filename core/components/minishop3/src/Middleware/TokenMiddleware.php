<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware для проверки токена авторизации (Web API)
 *
 * Проверяет наличие токена в заголовке HTTP_MS3TOKEN и сохраняет его в сессию.
 * Для публичных endpoints (cart/get, product/get) токен опционален.
 */
class TokenMiddleware implements MiddlewareInterface
{
    /** @var modX */
    private modX $modx;

    /** @var array Маршруты, не требующие токена */
    private array $publicRoutes = [
        '/api/v1/cart/get',
        '/api/v1/product/get',
        '/api/v1/product/list',
        '/api/v1/customer/token/get',
        '/api/v1/customer/token/refresh',
        '/api/v1/health',
    ];

    /**
     * @param modX $modx Экземпляр MODX
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Обработать запрос
     *
     * @param array $params URL параметры из роутера
     * @return Response|null Вернуть Response для прерывания, или null для продолжения
     */
    public function handle(array $params)
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Публичные endpoints не требуют токена
        if ($this->isPublicRoute($uri)) {
            return null; // Продолжить выполнение
        }

        // Получаем токен из заголовка
        $token = $_SERVER['HTTP_MS3TOKEN'] ?? '';

        // Альтернативно можно передать токен в параметре
        if (empty($token)) {
            $token = $_REQUEST['token'] ?? '';
        }

        // Проверяем наличие токена
        if (empty($token)) {
            return Response::error('ms3_err_token', 401);
        }

        // Сохраняем токен в сессию для совместимости со старым кодом
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['ms3']['customer_token'] = $token;

        return null; // Продолжить выполнение
    }

    /**
     * Проверить, является ли маршрут публичным
     *
     * @param string $uri URI запроса
     * @return bool
     */
    private function isPublicRoute(string $uri): bool
    {
        // Удаляем query string
        $path = parse_url($uri, PHP_URL_PATH);

        // Удаляем api.php из начала пути если есть
        $path = preg_replace('#^/assets/components/minishop3/api\.php#', '', $path);

        foreach ($this->publicRoutes as $publicRoute) {
            if (str_starts_with($path, $publicRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Добавить публичный маршрут
     *
     * @param string $route Маршрут
     * @return void
     */
    public function addPublicRoute(string $route): void
    {
        $this->publicRoutes[] = $route;
    }

    /**
     * Установить список публичных маршрутов
     *
     * @param array $routes Массив маршрутов
     * @return void
     */
    public function setPublicRoutes(array $routes): void
    {
        $this->publicRoutes = $routes;
    }
}
