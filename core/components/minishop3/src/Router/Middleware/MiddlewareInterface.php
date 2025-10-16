<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\Response;

/**
 * Interface для всех middleware
 */
interface MiddlewareInterface
{
    /**
     * Обработать запрос
     *
     * @param array $params URL параметры
     * @return Response|null Вернуть Response для прерывания, или null для продолжения
     */
    public function handle(array $params);
}
