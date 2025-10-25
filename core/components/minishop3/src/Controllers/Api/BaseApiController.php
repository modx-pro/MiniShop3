<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Базовый API контроллер
 */
abstract class BaseApiController
{
    /** @var modX */
    protected $modx;

    /** @var \MiniShop3\MiniShop3 */
    protected $ms3;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->ms3 = $modx->services->get('ms3');
    }

    /**
     * Получить параметры из запроса
     */
    protected function getRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                return $_GET;

            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

                // JSON
                if (strpos($contentType, 'application/json') !== false) {
                    $rawData = file_get_contents('php://input');
                    $data = json_decode($rawData, true);
                    return $data ?: [];
                }

                // Form data
                return $_POST;

            default:
                return [];
        }
    }

    /**
     * Получить ID текущего пользователя
     */
    protected function getUserId(): int
    {
        return (int) $this->modx->user->get('id');
    }

    /**
     * Проверить, авторизован ли пользователь
     */
    protected function isAuthenticated(string $context = 'web'): bool
    {
        return $this->modx->user && $this->modx->user->isAuthenticated($context);
    }
}
