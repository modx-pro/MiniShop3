<?php

/** @var \MODX\Revolution\modX $modx */

use MiniShop3\Model\msCustomerProfile;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modUser;
use MODX\Revolution\modSystemEvent;

switch ($modx->event->name) {
    case 'OnMODXInit':
        // Load extensions
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        if ($ms3) {
            $ms3->loadMap();
        }
        break;

    case 'OnHandleRequest':
        // Handle ajax requests
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
        if (empty($_REQUEST['ms3_action']) || !$isAjax) {
            return;
        }
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        if ($ms3) {
            $response = $ms3->handleRequest($_REQUEST['ms3_action'], @$_POST);
            if (is_array($response)) {
                $response = json_encode($response, JSON_UNESCAPED_UNICODE);
            }
            echo $response;
            exit();
        }
        break;

    case 'OnManagerPageBeforeRender':
        /** @var \MiniShop3\MiniShop3 $ms3 */
        if ($ms3 = $modx->services->get('ms3')) {
            $modx->controller->addLexiconTopic('minishop3:default');
            $modx->regClientStartupScript($ms3->config['jsUrl'] . 'mgr/misc/ms3.manager.js');
        }
        break;

    case 'OnLoadWebDocument':
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        if ($ms3) {
            $ms3->initialize();
            $ms3->registerFrontend();
        }
        // Handle non-ajax requests
        if (!empty($_REQUEST['ms3_action'])) {
            if ($ms3) {
                $ms3->handleRequest($_REQUEST['ms3_action'], @$_POST);
            }
        }
        // Set product fields as [[*resource]] tags
        if ($modx->resource->get('class_key') == MiniShop3\Model\msProduct::class) {
            if ($dataMeta = $modx->getFieldMeta(MiniShop3\Model\msProductData::class)) {
                unset($dataMeta['id']);
                $modx->resource->_fieldMeta = array_merge(
                    $modx->resource->_fieldMeta,
                    $dataMeta
                );
            }
        }
        break;

    case 'OnWebPageInit':
        // Set referrer cookie
        /** @var msCustomerProfile $profile */
        $referrerVar = $modx->getOption('ms3_referrer_code_var', null, 'msfrom', true);
        $cookieVar = $modx->getOption('ms3_referrer_cookie_var', null, 'msreferrer', true);
        $cookieTime = $modx->getOption('ms3_referrer_time', null, 86400 * 365, true);

        if (!$modx->user->isAuthenticated() && !empty($_REQUEST[$referrerVar])) {
            $code = trim($_REQUEST[$referrerVar]);
            if ($profile = $modx->getObject('msCustomerProfile', ['referrer_code' => $code])) {
                $referrer = $profile->get('id');
                setcookie($cookieVar, $referrer, time() + $cookieTime);
            }
        }
        break;

    case 'OnUserSave':
        // Save referrer id
        /** @var string $mode */
        if ($mode == modSystemEvent::MODE_NEW) {
            /** @var modUser $user */
            $cookieVar = $modx->getOption('ms3_referrer_cookie_var', null, 'msreferrer', true);
            $cookieTime = $modx->getOption('ms3_referrer_time', null, 86400 * 365, true);
            if ($modx->context->key != 'mgr' && !empty($_COOKIE[$cookieVar])) {
                if ($profile = $modx->getObject('msCustomerProfile', ['id' => $user->get('id')])) {
                    if (!$profile->get('referrer_id') && $_COOKIE[$cookieVar] != $user->get('id')) {
                        $profile->set('referrer_id', (int)$_COOKIE[$cookieVar]);
                        $profile->save();
                    }
                }
                setcookie($cookieVar, '', time() - $cookieTime);
            }
        }
        break;

    case 'msOnChangeOrderStatus':
        // Update customer stat
        if (empty($status) || $status != 2) {
            return;
        }

        /** @var modUser $user */
        /** @var msOrder $order */
        if ($user = $order->getOne('User')) {
            $q = $modx->newQuery('msOrder', ['type' => 0]);
            $q->innerJoin('modUser', 'modUser', ['modUser.id = msOrder.user_id']);
            $q->innerJoin('msOrderLog', 'msOrderLog', [
                'msOrderLog.order_id = msOrder.id',
                'msOrderLog.action' => 'status',
                'msOrderLog.entry' => $status,
            ]);
            $q->where(['msOrder.user_id' => $user->get('id')]);
            $q->groupby('msOrder.user_id');
            $q->select('SUM(msOrder.cost)');
            if ($q->prepare() && $q->stmt->execute()) {
                $spent = $q->stmt->fetchColumn();
                /** @var msCustomerProfile $profile */
                if ($profile = $modx->getObject('msCustomerProfile', ['id' => $user->get('id')])) {
                    $profile->set('spent', $spent);
                    $profile->save();
                }
            }
        }
        break;
}
