<?php

/** @var xPDOTransport $transport */
/** @var array $options */

/** @var modX $modx */

use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modSystemSetting;

if ($transport->xpdo) {
    $modx = $transport->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modx->addPackage('MiniShop3\Model', MODX_CORE_PATH . 'components/minishop3/src/', null, 'MiniShop3\\');
            $lang = $modx->getOption('manager_language') === 'en' ? 1 : 0;

            $statuses = [
                1 => [
                    'name' => !$lang ? 'Новый' : 'New',
                    'color' => '000000',
                    'email_user' => 1,
                    'email_manager' => 1,
                    'subject_user' => '[[%ms_email_subject_new_user]]',
                    'subject_manager' => '[[%ms_email_subject_new_manager]]',
                    'body_user' => 'tpl.msEmail.new.user',
                    'body_manager' => 'tpl.msEmail.new.manager',
                    'final' => 0,
                    'fixed' => 1,
                ],
                2 => [
                    'name' => !$lang ? 'Оплачен' : 'Paid',
                    'color' => '008000',
                    'email_user' => 1,
                    'email_manager' => 1,
                    'subject_user' => '[[%ms_email_subject_paid_user]]',
                    'subject_manager' => '[[%ms_email_subject_paid_manager]]',
                    'body_user' => 'tpl.msEmail.paid.user',
                    'body_manager' => 'tpl.msEmail.paid.manager',
                    'final' => 0,
                    'fixed' => 1,
                ],
                3 => [
                    'name' => !$lang ? 'Отправлен' : 'Sent',
                    'color' => '003366',
                    'email_user' => 1,
                    'email_manager' => 0,
                    'subject_user' => '[[%ms_email_subject_sent_user]]',
                    'subject_manager' => '',
                    'body_user' => 'tpl.msEmail.sent.user',
                    'body_manager' => '',
                    'final' => 1,
                    'fixed' => 1,
                ],
                4 => [
                    'name' => !$lang ? 'Отменён' : 'Cancelled',
                    'color' => '800000',
                    'email_user' => 1,
                    'email_manager' => 0,
                    'subject_user' => '[[%ms_email_subject_cancelled_user]]',
                    'subject_manager' => '',
                    'body_user' => 'tpl.msEmail.cancelled.user',
                    'body_manager' => '',
                    'final' => 1,
                    'fixed' => 1,
                ],
                5 => [
                    'name' => !$lang ? 'Черновик' : 'Draft',
                    'color' => 'C0C0C0',
                    'email_user' => 0,
                    'email_manager' => 0,
                    'subject_user' => '',
                    'subject_manager' => '',
                    'body_user' => '',
                    'body_manager' => '',
                    'final' => 0,
                    'fixed' => 0,
                ],
            ];

            foreach ($statuses as $id => $properties) {
                if (!$status = $modx->getCount(msOrderStatus::class, ['id' => $id])) {
                    $status = $modx->newObject(msOrderStatus::class, array_merge([
                        'editable' => 0,
                        'active' => 1,
                        'position' => $id - 1,
                    ], $properties));
                    $status->set('id', $id);
                    /*@var modChunk $chunk */
                    if (!empty($properties['body_user'])) {
                        if ($chunk = $modx->getObject(modChunk::class, ['name' => $properties['body_user']])) {
                            $status->set('body_user', $chunk->get('id'));
                        }
                    }
                    if (!empty($properties['body_manager'])) {
                        if ($chunk = $modx->getObject(modChunk::class, ['name' => $properties['body_manager']])) {
                            $status->set('body_manager', $chunk->get('id'));
                        }
                    }
                    $status->save();
                }
            }

            /** @var msDelivery $delivery */
            $delivery = $modx->getObject(msDelivery::class, 1);
            if (!$delivery) {
                $delivery = $modx->newObject(msDelivery::class);
                $delivery->fromArray([
                    'id' => 1,
                    'name' => !$lang ? 'Самовывоз' : 'Self-delivery',
                    'price' => 0,
                    'weight_price' => 0,
                    'distance_price' => 0,
                    'active' => 1,
                    'requires' => 'email,receiver',
                    'position' => 0,
                ], '', true);
                $delivery->save();
            }

            /** @var msPayment $payment */
            $payment = $modx->getObject(msPayment::class, 1);
            if (!$payment) {
                $payment = $modx->newObject(msPayment::class);
                $payment->fromArray([
                    'id' => 1,
                    'name' => !$lang ? 'Оплата наличными' : 'Cash',
                    'active' => 1,
                    'position' => 0,
                ], '', true);
                $payment->save();
            }

            /** @var msDeliveryMember $member */
            $member = $modx->getObject(msDeliveryMember::class, ['payment_id' => 1, 'delivery_id' => 1]);
            if (!$member) {
                $member = $modx->newObject(msDeliveryMember::class);
                $member->fromArray([
                    'payment_id' => 1,
                    'delivery_id' => 1,
                ], '', true);
                $member->save();
            }

            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms_order_product_fields']);
            if ($setting) {
                $value = $setting->get('value');
                if (strpos($value, 'product_pagetitle') !== false) {
                    $value = str_replace('product_pagetitle', 'name', $value);
                    $setting->set('value', $value);
                    $setting->save();
                }
            }

            /** @var modSystemSetting $setting */
            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms_chunks_categories']);
            if ($setting) {
                if (!$setting->get('editedon')) {
                    /** @var modCategory $category */
                    if ($category = $modx->getObject(modCategory::class, ['category' => 'MiniShop3'])) {
                        $setting->set('value', $category->get('id'));
                        $setting->save();
                    }
                }
            }

            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms_order_address_fields']);
            if ($setting) {
                $fields = explode(',', $setting->get('value'));
                $fields = array_unique(array_merge($fields, ['entrance', 'floor', 'text_address']));
                $setting->set('value', implode(',', $fields));
                $setting->save();
            }

            $chunks_descriptions = [
                'msProduct.content' => !$lang ? 'Чанк вывода карточки товара.' : 'Chunk for displaying card of MiniShop3 product.',
                'tpl.msProducts.row' => !$lang ? 'Чанк товара MiniShop3.' : 'Chunk for listing MiniShop3 catalog.',

                'tpl.msCart' => !$lang ? 'Чанк вывода корзины MiniShop3.' : 'Chunk for MiniShop3 cart.',
                'tpl.msMiniCart' => !$lang ? 'Чанк вывода мини корзины MiniShop3.' : 'Chunk for MiniShop3 mini cart.',
                'tpl.msOrder' => !$lang ? 'Чанк вывода формы оформления заказа MiniShop3.' : 'Chunk for displaying order form of MiniShop3.',
                'tpl.msGetOrder' => !$lang ? 'Чанк вывода заказа MiniShop3.' : 'Chunk for displaying order of MiniShop3.',
                'tpl.msOptions' => !$lang ? 'Чанк вывода дополнительных свойств товара MiniShop3.' : 'Chunk for displaying additional product characteristics of MiniShop3 product.',
                'tpl.msProductOptions' => !$lang ? 'Чанк вывода дополнительных опций товара MiniShop3.' : 'Chunk for displaying additional product options of MiniShop3 product.',
                'tpl.msGallery' => !$lang ? 'Чанк вывода галереи товара MiniShop3.' : 'Chunk for displaying gallery of MiniShop3 product.',

                'tpl.msEmail' => !$lang ? 'Базовый чанк оформления писем MiniShop3.' : 'Basic mail chunk of MiniShop3 mail.',
                'tpl.msEmail.new.user' => !$lang ? 'Чанк письма нового заказа пользователю.' : 'User new order mail chunk.',
                'tpl.msEmail.new.manager' => !$lang ? 'Чанк письма нового заказа менеджеру.' : 'Manager new order mail chunk.',
                'tpl.msEmail.paid.user' => !$lang ? 'Чанк письма оплаченного заказа пользователю.' : 'User paid order mail chunk.',
                'tpl.msEmail.paid.manager' => !$lang ? 'Чанк письма оплаченного заказа менеджеру.' : 'Manager paid order mail chunk.',
                'tpl.msEmail.sent.user' => !$lang ? 'Чанк письма отправленного заказа пользователю.' : 'User sent order mail chunk.',
                'tpl.msEmail.cancelled.user' => !$lang ? 'Чанк письма отмененного заказа пользователю.' : 'User cancelled order mail chunk.',
            ];

            foreach ($chunks_descriptions as $name => $description) {
                /** @var modChunk $chunk */
                if ($chunk = $modx->getObject(modChunk::class, ['name' => $name])) {
                    if (!$chunk->get('locked') && empty($chunk->get('description'))) {
                        $chunk->set('description', $description);
                        $chunk->save();
                    }
                }
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            $modx->removeCollection(modSystemSetting::class, [
                'namespace' => 'MiniShop3',
            ]);
            break;
    }
}
return true;
