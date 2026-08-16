<?php
/**
 * Frontend API Entry Point для MiniShop3
 *
 * Обрабатывает публичные API запросы от фронтенда:
 * - Получение токена покупателя
 * - Корзина (добавление, удаление, изменение)
 * - Заказы (оформление, получение данных)
 * - Каталог товаров (публичные данные)
 *
 * Использование:
 * api.php?route=/api/v1/customer/token/get
 * api.php?route=/api/v1/product/list
 * api.php?route=/api/v1/product/get/123
 * api.php?route=/api/v1/cart/add
 *
 * @package MiniShop3
 */

// JSON Content-Type выставляется только при отдаче тела (не при HTTP redirect)

// Проверяем наличие параметра route
if (empty($_REQUEST['route'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Route parameter is required',
        'code' => 400
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$route = $_REQUEST['route'];

try {
    // Загружаем MODX
    require_once dirname(__FILE__, 4) . '/config.core.php';
    require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

    /** @var \MODX\Revolution\modX $modx */
    $modx = new \MODX\Revolution\modX();
    $modx->initialize('web');

    // Загружаем autoloader компонента для классов роутера
    $componentPath = MODX_CORE_PATH . 'components/minishop3/';
    $autoloader = $componentPath . 'vendor/autoload.php';

    if (!file_exists($autoloader)) {
        throw new \Exception('Component not properly installed. Autoloader not found.');
    }

    require_once $autoloader;

    // Проверяем наличие сервиса (Issue #68: get() бросает Exception при отсутствии)
    if (!$modx->services->has('ms3')) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Service unavailable',
            'code' => 503
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Page culture for lexicon: ctx from ms3Config (same source as route — $_REQUEST) (#541)
    $rawCtx = $_REQUEST['ctx'] ?? null;
    $ctx = \MiniShop3\Services\Api\WebApiContextResolver::apply(
        $modx,
        is_string($rawCtx) ? $rawCtx : null
    );

    $ms3 = $modx->services->get('ms3');
    $ms3->initialize($ctx);

    // Создаём роутер — только Web API (фронтенд); manager connector не грузит эти пути (#384)
    $router = new \MiniShop3\Router\Router($modx);
    $router->loadWebRoutes($componentPath, MODX_CORE_PATH);
    $router->build();

    // Обрабатываем запрос
    $response = $router->dispatch($route, $_SERVER['REQUEST_METHOD']);

    $statusCode = $response->getStatusCode();
    $redirectUrl = $response->getRedirectUrl();
    if ($redirectUrl !== null && $redirectUrl !== '') {
        http_response_code($statusCode);
        header('Location: ' . $redirectUrl);
        exit;
    }

    $responseData = $response->getData();

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($responseData, JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    // Возвращаем ошибку (Throwable: TypeError from null context must not escape as bare 500)
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Internal server error',
        'code' => 500
    ];

    // Логируем и показываем детали только если MODX успешно инициализирован
    if (isset($modx)) {
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3 Action] ' . $e->getMessage());
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3 Action] Stack trace: ' . $e->getTraceAsString());

        // В режиме разработки показываем детали ошибки
        if ($modx->getOption('debug', null, false)) {
            $response['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
