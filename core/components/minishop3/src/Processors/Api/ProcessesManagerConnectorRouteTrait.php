<?php

namespace MiniShop3\Processors\Api;

use MiniShop3\Router\Router as ApiRouter;

/**
 * Shared connector.php dispatch for manager API routes (Index / Router).
 *
 * Manager routes only via Router::loadManagerRoutes(); storefront /api/v1 rejected (#384).
 */
trait ProcessesManagerConnectorRouteTrait
{
    /**
     * {@inheritdoc}
     */
    public function process()
    {
        try {
            $route = trim((string)$this->getProperty('route', ''));

            if ($route === '') {
                http_response_code(400);

                return $this->failure('Route parameter is required', ['code' => 400]);
            }

            if (ApiRouter::isStorefrontRoute($route)) {
                http_response_code(404);

                return $this->failure(
                    'Storefront API is not available via manager connector. Use api.php.',
                    ['code' => 404]
                );
            }

            $componentPath = MODX_CORE_PATH . 'components/minishop3/';
            $autoloader = $componentPath . 'vendor/autoload.php';

            if (!file_exists($autoloader)) {
                http_response_code(500);

                return $this->failure('Component not properly installed. Run: composer install', ['code' => 500]);
            }

            $managerRoutes = $componentPath . 'config/routes/manager.php';
            if (!file_exists($managerRoutes)) {
                http_response_code(500);

                return $this->failure('System routes not found: ' . $managerRoutes, ['code' => 500]);
            }

            if (!class_exists('FastRoute\\simpleDispatcher')) {
                require_once $autoloader;
            }

            $router = new ApiRouter($this->modx);
            $router->loadManagerRoutes($componentPath, MODX_CORE_PATH);
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
        } catch (\Throwable $e) {
            // TypeError/Error must stay JSON — display_errors HTML breaks Vue request.json() (#531/#532).
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3 API] ' . $e->getMessage());
            http_response_code(500);

            return $this->failure(
                $this->modx->getOption('ms3_api_debug', null, false)
                    ? $e->getMessage()
                    : 'Internal server error',
                ['code' => 500]
            );
        }
    }
}
