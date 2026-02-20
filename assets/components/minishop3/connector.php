<?php

/** @noinspection PhpIncludeInspection */

use MODX\Revolution\modConnectorRequest;

require_once dirname(__FILE__, 4) . '/config.core.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CONNECTORS_PATH . 'index.php';

/** @var modX $modx */
if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Service unavailable', 'code' => 503], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @var \MiniShop3\MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$modx->lexicon->load('minishop3:default', 'minishop3:manager');

$path = $modx->getOption('processorsPath', $ms3->config, MODX_CORE_PATH . 'components/minishop3/src/Processors/');

if (!empty($_REQUEST['class_key'])) {
    $action = $_REQUEST['action'];
    $tmp = explode('/', $action);
    $action = $tmp[count($tmp) - 1];

    switch ($_REQUEST['class_key']) {
        case 'MiniShop3\Model\msProduct':
            $_REQUEST['action'] = 'MiniShop3\Processors\Product\\' . $action;
            break;
        case 'MiniShop3\Model\msCategory':
            $_REQUEST['action'] = 'MiniShop3\Processors\Category\\' . $action;
            break;
    }

    if ($action === 'Reload') {
        $_REQUEST['action'] = 'Resource/Reload';
    }
}

/** @var modConnectorRequest $request */
$request = $modx->request;
$request->handleRequest([
    'processors_path' => $path,
    'location' => '',
]);
