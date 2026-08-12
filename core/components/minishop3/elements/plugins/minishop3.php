<?php
/**
 * MiniShop3 Plugin
 *
 * Events:
 * - OnMODXInit: Load extra fields through ExtraFields
 * - OnLoadWebDocument: Initialize frontend, register product fields as [[*resource]] tags
 * - OnManagerPageBeforeRender: Load lexicon and JS in admin panel
 * - OnDocFormSave: Handle resource-to-product conversion
 * - OnUserSave: Synchronize msCustomer ↔ modUser (create/update)
 * - OnBeforeUserFormSave: Synchronize msCustomer when modUser profile changes
 * - OnUserRemove: Unlink msCustomer from deleted modUser
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $scriptProperties
 */

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msProduct;
use MiniShop3\Services\Product\ProductService;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modX;

switch ($modx->event->name) {
    case 'OnMODXInit':
        if (!$modx->services->has('ms3')) {
            $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
            break;
        }
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        $ms3->loadMap();
        break;

    case 'OnManagerPageBeforeRender':
        if (!$modx->services->has('ms3')) {
            $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
            break;
        }
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        $modx->controller->addLexiconTopic('minishop3:default');
        $modx->regClientStartupScript($ms3->config['jsUrl'] . 'mgr/misc/ms3.manager.js');

        $syncEnabled = (bool)$modx->getOption('ms3_customer_sync_enabled', null, false);
        if ($syncEnabled && $modx->user && $modx->user->hasSessionContext('mgr')) {
            $modx->lexicon->load('minishop3:customer');
        }
        break;

    case 'OnLoadWebDocument':
        if (!$modx->services->has('ms3')) {
            $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[MiniShop3] Service not registered');
            break;
        }
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        $ctx = $modx->context->key ?? 'web'; // ms3Config.ctx → Web API lexicon (#541)
        $ms3->initialize($ctx);
        $ms3->registerFrontend($ctx);

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

    /**
     * OnDocFormSave - handle resource-to-product conversion
     *
     * Delegates to ProductService::handleConversion()
     */
    case 'OnDocFormSave':
        /** @var \MODX\Revolution\modResource $resource */
        if (!isset($resource)) {
            break;
        }

        // Only process msProduct resources
        if ($resource->get('class_key') !== msProduct::class) {
            break;
        }

        /** @var ProductService $productService */
        $productService = $modx->services->get('ms3_product_service');
        $productService->handleConversion($resource);
        break;

    /**
     * OnUserSave / OnBeforeUserFormSave - create/update msCustomer when modUser is saved
     *
     * Provides hybrid synchronization between msCustomer ↔ modUser:
     * 1. Automatic msCustomer creation on modUser registration
     * 2. Data synchronization (email, name, phone, active status)
     * 3. Link existing msCustomer to modUser by email
     */
    case 'OnUserSave':
    case 'OnBeforeUserFormSave':
        $syncEnabled = (bool)$modx->getOption('ms3_customer_sync_enabled', null, false);
        if (!$syncEnabled) {
            break;
        }

        /** @var modUser $user */
        if (!isset($user) || !$user instanceof modUser) {
            break;
        }

        $userId = $user->get('id');
        if (!$userId) {
            break;
        }

        /** @var modUserProfile $profile */
        $profile = $user->getOne('Profile');
        if (!$profile) {
            break;
        }

        $email = $profile->get('email');
        if (empty($email)) {
            break;
        }

        /** @var msCustomer $customer */
        $customer = $modx->getObject(msCustomer::class, ['user_id' => $userId]);

        if (!$customer) {
            $customer = $modx->getObject(msCustomer::class, ['email' => $email]);

            if ($customer) {
                $customer->set('user_id', $userId);
                $modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[MiniShop3] Linked existing msCustomer #{$customer->id} to modUser #{$userId}"
                );
            }
        }

        if (!$customer) {
            $customer = $modx->newObject(msCustomer::class);
            $customer->set('user_id', $userId);
            $customer->set('email', $email);
            $customer->set('is_active', $user->get('active'));
            $customer->set('token', bin2hex(random_bytes(32)));

            $modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3] Created msCustomer for modUser #{$userId}"
            );
        }

        $customer->set('first_name', $profile->get('fullname') ?: '');
        $customer->set('last_name', '');
        $customer->set('phone', $profile->get('phone') ?: '');
        $customer->set('is_active', $user->get('active'));

        $customer->save();
        break;

    /**
     * OnUserRemove - unlink msCustomer from deleted modUser
     *
     * When modUser is deleted, unlinks the associated msCustomer (user_id = 0),
     * but does NOT delete it to preserve order history.
     */
    case 'OnUserRemove':
        $syncEnabled = (bool)$modx->getOption('ms3_customer_sync_enabled', null, false);
        if (!$syncEnabled) {
            break;
        }

        /** @var modUser $user */
        if (!isset($user) || !$user instanceof modUser) {
            break;
        }

        $deleteWithUser = (bool)$modx->getOption('ms3_customer_sync_delete_with_user', null, false);
        if (!$deleteWithUser) {
            break;
        }

        $userId = $user->get('id');

        /** @var msCustomer $customer */
        $customer = $modx->getObject(msCustomer::class, ['user_id' => $userId]);

        if ($customer) {
            $customer->set('user_id', 0);
            $customer->save();

            $modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3] Unlinked msCustomer #{$customer->id} from deleted modUser #{$userId}"
            );
        }
        break;

    // OnCategoryRemove handler removed in #10 — msOption no longer references modCategory.
    // Options now belong to msOptionGroup, which is independent of the MODX category tree.
}
