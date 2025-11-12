<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\Response;

/**
 * Middleware для обработки CORS (Cross-Origin Resource Sharing)
 *
 * Позволяет API работать с запросами из браузера с других доменов.
 * Необходимо для headless frontend (Vue, React) на отдельных доменах.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var array Разрешённые домены (origins) */
    private array $allowedOrigins;

    /** @var array Разрешённые HTTP методы */
    private array $allowedMethods;

    /** @var array Разрешённые заголовки */
    private array $allowedHeaders;

    /** @var bool Разрешить credentials (cookies, auth headers) */
    private bool $allowCredentials;

    /** @var int Время кеширования preflight запроса (в секундах) */
    private int $maxAge;

    /**
     * @param array $config Конфигурация CORS
     */
    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $config['allowed_origins'] ?? ['*'];
        $this->allowedMethods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'MS3TOKEN'];
        $this->allowCredentials = $config['allow_credentials'] ?? true;
        $this->maxAge = $config['max_age'] ?? 86400; // 24 часа
    }

    /**
     * Обработать запрос
     *
     * @param array $params URL параметры из роутера
     * @return Response|null Вернуть Response для прерывания, или null для продолжения
     */
    public function handle(array $params)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Проверяем, разрешён ли origin
        if ($this->isOriginAllowed($origin)) {
            $this->setCorsHeaders($origin);
        }

        // Для preflight запросов (OPTIONS) сразу возвращаем 200
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return null; // Продолжить выполнение
    }

    /**
     * Проверить, разрешён ли origin
     *
     * @param string $origin Origin из заголовка
     * @return bool
     */
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Если разрешены все origins
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        // Проверяем точное совпадение
        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }

        // Проверяем wildcard паттерны (например: *.example.com)
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if (str_contains($allowedOrigin, '*')) {
                $pattern = str_replace('*', '.*', $allowedOrigin);
                if (preg_match('#^' . $pattern . '$#', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Установить CORS заголовки
     *
     * @param string $origin Origin для заголовка Access-Control-Allow-Origin
     * @return void
     */
    private function setCorsHeaders(string $origin): void
    {
        // Для wildcard origin (*) не можем использовать credentials
        if (in_array('*', $this->allowedOrigins) && !$this->allowCredentials) {
            header('Access-Control-Allow-Origin: *');
        } else {
            // Для конкретного origin можем использовать credentials
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        if ($this->allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Max-Age: ' . $this->maxAge);
    }
}
