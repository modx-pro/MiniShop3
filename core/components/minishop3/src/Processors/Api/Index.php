<?php

namespace MiniShop3\Processors\Api;

use MiniShop3\Router\Router as ApiRouter;
use MODX\Revolution\Processors\Processor;

/**
 * Процессор для обработки API запросов через connector.php
 *
 * Использование:
 * connector.php?action=api&route=/api/mgr/test/success
 */
class Index extends Processor
{
    /**
     * {@inheritdoc}
     */
    public function process()
    {
        try {
            // Получаем маршрут из параметра
            $route = $this->getProperty('route', '');

            if (empty($route)) {
                return $this->failure('Route parameter is required', ['code' => 400]);
            }

            // Загружаем автолоадер компонента
            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $autoloader = $componentPath . 'vendor/autoload.php';

            if (!file_exists($autoloader)) {
                return $this->failure('Component not properly installed. Run: composer install', ['code' => 500]);
            }

            // ВСЕГДА загружаем autoloader для FastRoute и других зависимостей
            if (!class_exists('FastRoute\\simpleDispatcher')) {
                require_once $autoloader;
            }

            // Создаём роутер
            $router = new ApiRouter($this->modx);

            // 1. Загружаем СИСТЕМНЫЕ роуты (из компонента)
            $systemRoutesFile = $componentPath . 'config/routes.php';

            if (!file_exists($systemRoutesFile)) {
                return $this->failure('System routes not found: ' . $systemRoutesFile, ['code' => 500]);
            }

            $router->loadRoutes($systemRoutesFile);

            // 2. Загружаем ПОЛЬЗОВАТЕЛЬСКИЕ роуты (из core/config/, опционально)
            $customRoutesFile = MODX_CORE_PATH . 'config/ms3_routes.custom.php';

            if (file_exists($customRoutesFile)) {
                $router->loadRoutes($customRoutesFile);
            }

            // Строим dispatcher
            $router->build();

            // Обрабатываем запрос, передавая явно URI и метод
            // (не полагаемся на $_SERVER, так как он содержит путь к connector.php)
            $response = $router->dispatch($route, $_SERVER['REQUEST_METHOD']);

            // Получаем данные ответа
            $responseData = $response->getData();
            $statusCode = $response->getStatusCode();

            // Устанавливаем HTTP статус код для MODX connector
            http_response_code($statusCode);

            // Если успешный ответ - возвращаем данные напрямую
            if ($statusCode >= 200 && $statusCode < 300) {
                // MODX processor обернёт это в свой формат
                // Поэтому возвращаем внутренние данные
                return $this->success('', $responseData['data'] ?? $responseData);
            }

            // Если ошибка - используем MODX формат ошибки
            return $this->failure(
                $responseData['message'] ?? 'API request failed',
                $responseData
            );

        } catch (\Exception $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3 API] ' . $e->getMessage());

            return $this->failure(
                $this->modx->getOption('ms3_api_debug', null, false)
                    ? $e->getMessage()
                    : 'Internal server error',
                ['code' => 500]
            );
        }
    }
}
