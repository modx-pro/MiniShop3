<?php
/**
 * MiniShop3 Plugin
 *
 * События:
 * - OnMODXInit: Загрузка дополнительных полей через ExtraFields
 * - OnLoadWebDocument: Инициализация фронтенда, регистрация product fields как [[*resource]] tags
 * - OnManagerPageBeforeRender: Подключение лексикона и JS в админке
 * - OnUserSave: Синхронизация msCustomer ↔ modUser (создание/обновление)
 * - OnBeforeUserFormSave: Синхронизация msCustomer при изменении профиля modUser
 * - OnUserRemove: Отвязка msCustomer от удалённого modUser
 *
 * @var \MODX\Revolution\modX $modx
 * @var array $scriptProperties
 */

use MiniShop3\Model\msCustomer;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;

switch ($modx->event->name) {
    case 'OnMODXInit':
        // Load extensions
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        if ($ms3) {
            $ms3->loadMap();
        }
        break;

    case 'OnManagerPageBeforeRender':
        /** @var \MiniShop3\MiniShop3 $ms3 */
        if ($ms3 = $modx->services->get('ms3')) {
            $modx->controller->addLexiconTopic('minishop3:default');
            $modx->regClientStartupScript($ms3->config['jsUrl'] . 'mgr/misc/ms3.manager.js');
        }

        // Загрузка лексикона для синхронизации клиентов
        $syncEnabled = (bool)$modx->getOption('ms3_customer_sync_enabled', null, false);
        if ($syncEnabled && $modx->user && $modx->user->hasSessionContext('mgr')) {
            $modx->lexicon->load('minishop3:customer');
        }
        break;

    case 'OnLoadWebDocument':
        /** @var \MiniShop3\MiniShop3 $ms3 */
        $ms3 = $modx->services->get('ms3');
        if ($ms3) {
            $ms3->initialize();
            $ms3->registerFrontend();
        }

        // Set product fields as [[*resource]] tags
        // Позволяет использовать [[*price]], [[*article]] и т.д. в шаблонах продуктов
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
     * OnUserSave / OnBeforeUserFormSave - создание/обновление msCustomer при сохранении modUser
     *
     * Обеспечивает гибридную синхронизацию msCustomer ↔ modUser:
     * 1. Автоматическое создание msCustomer при регистрации modUser
     * 2. Синхронизация данных (email, имя, телефон, активность)
     * 3. Связывание существующего msCustomer с modUser по email
     */
    case 'OnUserSave':
    case 'OnBeforeUserFormSave':
        // Проверка, включена ли синхронизация
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
            break; // Пользователь еще не создан
        }

        /** @var modUserProfile $profile */
        $profile = $user->getOne('Profile');
        if (!$profile) {
            break;
        }

        $email = $profile->get('email');
        if (empty($email)) {
            break; // Email обязателен для msCustomer
        }

        // Поиск существующего msCustomer
        /** @var msCustomer $customer */
        $customer = $modx->getObject(msCustomer::class, ['user_id' => $userId]);

        if (!$customer) {
            // Поиск по email (возможно, клиент создан раньше)
            $customer = $modx->getObject(msCustomer::class, ['email' => $email]);

            if ($customer) {
                // Связываем существующего клиента с modUser
                $customer->set('user_id', $userId);
                $modx->log(
                    modX::LOG_LEVEL_INFO,
                    "[MiniShop3] Linked existing msCustomer #{$customer->id} to modUser #{$userId}"
                );
            }
        }

        // Создание нового msCustomer, если не найден
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

        // Синхронизация данных профиля
        $customer->set('first_name', $profile->get('fullname') ?: '');
        $customer->set('last_name', ''); // modUser не имеет отдельного last_name
        $customer->set('phone', $profile->get('phone') ?: '');

        // Синхронизация активности
        $customer->set('is_active', $user->get('active'));

        $customer->save();
        break;

    /**
     * OnUserRemove - отвязка msCustomer от удалённого modUser
     *
     * При удалении modUser отвязывает связанного msCustomer (user_id = 0),
     * но НЕ удаляет его, чтобы сохранить историю заказов.
     */
    case 'OnUserRemove':
        // Проверка, включена ли синхронизация
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
            // Отвязываем от modUser вместо удаления (сохраняем историю заказов)
            $customer->set('user_id', 0);
            $customer->save();

            $modx->log(
                modX::LOG_LEVEL_INFO,
                "[MiniShop3] Unlinked msCustomer #{$customer->id} from deleted modUser #{$userId}"
            );
        }
        break;
}
