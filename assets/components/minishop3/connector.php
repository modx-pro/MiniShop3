<?php

/** @noinspection PhpIncludeInspection */

use MODX\Revolution\modConnectorRequest;

require_once dirname(__FILE__, 4) . '/config.core.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
/** @noinspection PhpIncludeInspection */
require_once MODX_CONNECTORS_PATH . 'index.php';

/** @var modX $modx */
/** @var \MiniShop3\MiniShop3 $ms3 */
$ms3 = $modx->services->get('ms3');
$modx->lexicon->load('minishop3:default', 'minishop:manager');

$path = $modx->getOption('processorsPath', $ms3->config, MODX_CORE_PATH . 'components/minishop3/src/Processors/');
/** @var modConnectorRequest $request */
$request = $modx->request;
$request->handleRequest([
    'processors_path' => $path,
    'location' => '',
]);
