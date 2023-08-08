<?php

use xPDO\Transport\xPDOTransport;
use MODX\Revolution\modX;

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
            $modx->lexicon->load('minishop3:manager');

            /** @var msDelivery $delivery */
            $delivery = $modx->getObject(msDelivery::class, 1);
            if (!$delivery) {
                $delivery = $modx->newObject(msDelivery::class);
                $delivery->fromArray([
                    'id' => 1,
                    'name' => $modx->lexicon('ms3_order_delivery_self'),
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
                    'name' => $modx->lexicon('ms3_order_payment_cash'),
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

            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms3_order_product_fields']);
            if ($setting) {
                $value = $setting->get('value');
                if (strpos($value, 'product_pagetitle') !== false) {
                    $value = str_replace('product_pagetitle', 'name', $value);
                    $setting->set('value', $value);
                    $setting->save();
                }
            }

            /** @var modSystemSetting $setting */
            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms3_chunks_categories']);
            if ($setting) {
                if (!$setting->get('editedon')) {
                    /** @var modCategory $category */
                    if ($category = $modx->getObject(modCategory::class, ['category' => 'MiniShop3'])) {
                        $setting->set('value', $category->get('id'));
                        $setting->save();
                    }
                }
            }

            $setting = $modx->getObject(modSystemSetting::class, ['key' => 'ms3_order_address_fields']);
            if ($setting) {
                $fields = explode(',', $setting->get('value'));
                $fields = array_unique(array_merge($fields, ['entrance', 'floor', 'text_address']));
                $setting->set('value', implode(',', $fields));
                $setting->save();
            }

            $chunks_descriptions = [
                'msProduct.content' => $modx->lexicon('ms3_chunk_description_msproduct_content'),
                'tpl.msProducts.row' => $modx->lexicon('ms3_chunk_description_msproduct_row'),

                'tpl.msCart' => $modx->lexicon('ms3_chunk_description_mscart'),

                'tpl.msOrder' => $modx->lexicon('ms3_chunk_description_msorder'),
                'tpl.msGetOrder' => $modx->lexicon('ms3_chunk_description_msgetorder'),
                'tpl.msOptions' => $modx->lexicon('ms3_chunk_description_msoptions'),
                'tpl.msProductOptions' => $modx->lexicon('ms3_chunk_description_msproductoptions'),
                'tpl.msGallery' => $modx->lexicon('ms3_chunk_description_msgallery'),

                'tpl.msEmail' => $modx->lexicon('ms3_chunk_description_msemail'),
                'tpl.msEmail.new.customer' => $modx->lexicon('ms3_chunk_description_msemail_new_customer'),
                'tpl.msEmail.new.manager' => $modx->lexicon('ms3_chunk_description_msemail_new_manager'),
                'tpl.msEmail.paid.customer' => $modx->lexicon('ms3_chunk_description_msemail_paid_customer'),
                'tpl.msEmail.paid.manager' => $modx->lexicon('ms3_chunk_description_msemail_paid_manager'),
                'tpl.msEmail.sent.customer' => $modx->lexicon('ms3_chunk_description_msemail_sent_customer'),
                'tpl.msEmail.cancelled.customer' => $modx->lexicon('ms3_chunk_description_msemail_cancelled_customer'),
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
