<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware для проверки авторизации пользователя
 *
 * Для mgr контекста дополнительно проверяет HTTP_MODAUTH токен
 * как это делает MODX в connector запросах (см. modConnectorResponse)
 */
class AuthMiddleware implements MiddlewareInterface
{
    protected $modx;
    protected $context;

    /**
     * @param modX $modx
     * @param string $context Контекст (mgr или web)
     */
    public function __construct(modX $modx, string $context = 'mgr')
    {
        $this->modx = $modx;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params)
    {
        // Проверяем авторизацию в нужном контексте
        if (!$this->modx->user || !$this->modx->user->isAuthenticated($this->context)) {
            return Response::error('Unauthorized. Please log in.', 401);
        }

        // Для админки дополнительно проверяем HTTP_MODAUTH токен
        if ($this->context === 'mgr') {
            return $this->validateModAuthToken();
        }

        return null; // Продолжить выполнение
    }

    /**
     * Проверка HTTP_MODAUTH токена (как в MODX connector security)
     *
     * @return Response|null Возвращает Response с ошибкой если токен невалиден, иначе null
     */
    protected function validateModAuthToken()
    {
        // Получаем токен пользователя для текущего контекста
        $contextKey = $this->modx->context->get('key');
        $expectedToken = $this->modx->user->getUserToken($contextKey);

        // Проверяем наличие токена в запросе
        $providedToken = $_SERVER['HTTP_MODAUTH'] ?? $_REQUEST['HTTP_MODAUTH'] ?? null;

        if (!$providedToken) {
            return Response::error('Missing HTTP_MODAUTH token', 401);
        }

        // Безопасное сравнение токенов (защита от timing attacks)
        if (!hash_equals($expectedToken, $providedToken)) {
            return Response::error('Invalid HTTP_MODAUTH token', 401);
        }

        return null; // Токен валидный, продолжить выполнение
    }
}
