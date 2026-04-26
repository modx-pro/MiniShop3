<?php

namespace MiniShop3\Middleware;

use MiniShop3\Router\Middleware\MiddlewareInterface;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware для проверки наличия сервиса ms3 (Issue #68).
 *
 * Выполняет has('ms3') до вызова контроллеров.
 * При отсутствии сервиса возвращает 503 вместо необработанного Exception.
 */
class ServiceCheckMiddleware implements MiddlewareInterface
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @param array $params
     * @return Response|null
     */
    public function handle(array $params)
    {
        if (!$this->modx->services->has('ms3')) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
            return Response::error('Service unavailable', HttpStatus::SERVICE_UNAVAILABLE);
        }

        return null;
    }
}
