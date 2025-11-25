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
 * api.php?route=/api/v1/cart/add
 *
 * @package MiniShop3
 */

// Устанавливаем заголовки для JSON API
header('Content-Type: application/json; charset=utf-8');

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

    // Инициализируем сервис MiniShop3
    if (!$modx->services->has('ms3')) {
        $modx->services->add('ms3', function() use ($modx) {
            return new \MiniShop3\MiniShop3($modx);
        });
    }

    // Получаем сервис и инициализируем его (для регистрации корзины и других сервисов)
    $ms3 = $modx->services->get('ms3');
    $ms3->initialize('web');

    // Создаём роутер
    $router = new \MiniShop3\Router\Router($modx);

    // Загружаем ТОЛЬКО Web API роуты (фронтенд, публичные)
    $webRoutesFile = $componentPath . 'config/routes/web.php';

    if (!file_exists($webRoutesFile)) {
        throw new \Exception('Web routes not found: ' . $webRoutesFile);
    }

    $router->loadRoutes($webRoutesFile);

    // Загружаем пользовательские роуты (опционально)
    $customRoutesFile = MODX_CORE_PATH . 'config/ms3_routes_web.custom.php';
    if (file_exists($customRoutesFile)) {
        $router->loadRoutes($customRoutesFile);
    }

    // Строим dispatcher
    $router->build();

    // Обрабатываем запрос
    $response = $router->dispatch($route, $_SERVER['REQUEST_METHOD']);

    // Получаем данные ответа
    $responseData = $response->getData();
    $statusCode = $response->getStatusCode();

    // Устанавливаем HTTP статус код
    http_response_code($statusCode);

    // Выводим JSON
    echo json_encode($responseData, JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {

    // Возвращаем ошибку
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

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
