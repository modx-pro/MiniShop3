<?php

namespace MiniShop3\Processors\Api;

use MiniShop3\Router\Response;
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

            // Sanitize before Processor success()/failure() → modConnectorResponse → xPDO::toJSON()
            // which has no UTF-8 fallback (#671 / #654).
            return $this->respondFromRouter($responseData, $statusCode);
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

    /**
     * Map router Response payload to MODX Processor success()/failure() after UTF-8 sanitize (#671).
     *
     * @param mixed $responseData Router Response::getData()
     * @internal Exposed for unit tests of the manager connector reshape path.
     */
    protected function respondFromRouter(mixed $responseData, int $statusCode): mixed
    {
        $payload = is_array($responseData) ? $responseData : ['code' => $statusCode];
        $clean = Response::sanitizeUtf8ForJson($payload, $this->modx);
        if (!is_array($clean)) {
            return $this->connectorJsonEncodeFailure();
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return $this->success('', $clean['data'] ?? $clean);
        }

        $message = (string) ($clean['message'] ?? 'API request failed');

        return $this->failure($message !== '' ? $message : 'API request failed', $clean);
    }

    private function connectorJsonEncodeFailure(): mixed
    {
        http_response_code(500);

        return $this->failure('Internal server error', ['code' => 500]);
    }
}
