<?php

namespace MiniShop3\Processors\Api;

use MiniShop3\Router\Router as ApiRouter;
use MODX\Revolution\Processors\Processor;

/**
 * Processor for handling API requests through connector.php (встроенная админка MODX, сессия менеджера).
 *
 * Загружаются только manager-роуты: системный manager.php, ms3_routes_manager.custom.php, ms3.routes.d/manager/.
 * Web-роуты (web.php, ms3.routes.d/web/) сюда не подключаются — для них отдельные точки входа: api.php и Processors\Api\Index.
 *
 * Usage:
 * connector.php?action=api&route=/api/mgr/test/success
 */
class Router extends Processor
{
    /**
     * {@inheritdoc}
     */
    public function process()
    {
        try {
            $route = $this->getProperty('route', '');

            if (empty($route)) {
                return $this->failure('Route parameter is required', ['code' => 400]);
            }

            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $autoloader = $componentPath . 'vendor/autoload.php';

            if (!file_exists($autoloader)) {
                return $this->failure('Component not properly installed. Run: composer install', ['code' => 500]);
            }

            if (!class_exists('FastRoute\\simpleDispatcher')) {
                require_once $autoloader;
            }

            $router = new ApiRouter($this->modx);

            $systemRoutesFile = $componentPath . 'config/routes/manager.php';

            if (!file_exists($systemRoutesFile)) {
                return $this->failure('System routes not found: ' . $systemRoutesFile, ['code' => 500]);
            }

            $router->loadRoutes($systemRoutesFile);

            $customRoutesFile = MODX_CORE_PATH . 'config/ms3_routes_manager.custom.php';

            if (file_exists($customRoutesFile)) {
                $router->loadRoutes($customRoutesFile);
            }

            $router->loadRoutesFromDirectory(ApiRouter::coreAddonRoutesDirectory('manager'));

            $router->build();

            $response = $router->dispatch($route, $_SERVER['REQUEST_METHOD']);

            $responseData = $response->getData();
            $statusCode = $response->getStatusCode();

            http_response_code($statusCode);

            if ($statusCode >= 200 && $statusCode < 300) {
                return $this->success('', $responseData['data'] ?? $responseData);
            }

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
