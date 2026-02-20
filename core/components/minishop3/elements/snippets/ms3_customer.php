<?php

use MiniShop3\MiniShop3;
use MiniShop3\Services\Customer\ProfilePageService;
use MiniShop3\Services\Customer\AddressesPageService;
use MiniShop3\Services\Customer\OrdersPageService;
use ModxPro\PdoTools\Fetch;

/** @var modX $modx */
/** @var array $scriptProperties */
/** @var MiniShop3 $ms3 */

if (!$modx->services->has('ms3')) {
    $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
    return '';
}

$ms3 = $modx->services->get('ms3');
$ms3->initialize($modx->context->key);

/** @var Fetch $pdoFetch */
$pdoFetch = $modx->services->get(Fetch::class);

$modx->lexicon->load('minishop3:customer');
$modx->lexicon->load('minishop3:default');
$modx->lexicon->load('minishop3:cart'); // For order details template

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (!empty($_SESSION['ms3']['customer_token'])) {
        $token = $_SESSION['ms3']['customer_token'];
        $tokenObj = $modx->getObject(\MiniShop3\Model\msCustomerToken::class, ['token' => $token]);
        if ($tokenObj) {
            $tokenObj->remove();
        }
    }

    if (isset($_SESSION['ms3'])) {
        unset($_SESSION['ms3']['customer_id']);
        unset($_SESSION['ms3']['customer_token']);
        unset($_SESSION['ms3']['customer_token_expires']);
    }

    $loginPageId = $modx->getOption('ms3_customer_login_page_id', null, 1);
    $modx->sendRedirect($modx->makeUrl($loginPageId));
    exit;
}

$service = $modx->getOption('service', $scriptProperties, 'profile');
$return = $modx->getOption('return', $scriptProperties, 'tpl');

try {
    $serviceClass = match ($service) {
        'profile' => ProfilePageService::class,
        'addresses' => AddressesPageService::class,
        'orders' => OrdersPageService::class,
        default => throw new \InvalidArgumentException(
            $modx->lexicon('ms3_customer_err_invalid_service', ['service' => $service])
        ),
    };

    /** @var \MiniShop3\Services\Customer\CustomerPageService $pageService */
    $pageService = new $serviceClass($modx, $ms3, $scriptProperties);

    if (!$pageService->checkAuth()) {
        if ($return === 'data') {
            return [
                'authorized' => false,
                'login_url' => $modx->makeUrl(
                    $modx->getOption('ms3_customer_login_page_id', null, 1)
                ),
                'register_url' => $modx->makeUrl(
                    $modx->getOption('ms3_customer_register_page_id', null, 1)
                ),
            ];
        }
        return $pageService->renderUnauthorized();
    }

    if ($return === 'data') {
        $data = $pageService->getData();
        $data['authorized'] = true;
        $data['service'] = $service;
        return $data;
    }

    return $pageService->render();

} catch (\InvalidArgumentException $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msCustomer] ' . $e->getMessage());
    return $modx->lexicon('ms3_customer_err_invalid_service', ['service' => $service]);
} catch (\Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[msCustomer] Error: ' . $e->getMessage());
    return $modx->lexicon('ms3_err_unknown');
}
