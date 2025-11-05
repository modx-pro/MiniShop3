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
            $modx->lexicon->load('minishop3:manager');

            // Создаём начальные данные через SQL, т.к. модели ещё не загружены
            $prefix = $modx->config['table_prefix'];

            // Проверяем и создаём доставку
            $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}ms3_deliveries WHERE id = 1");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $modx->prepare("INSERT INTO {$prefix}ms3_deliveries (id, name, price, weight_price, distance_price, active, validation_rules, position) VALUES (1, :name, 0, 0, 0, 1, :rules, 0)");
                $stmt->execute([
                    ':name' => $modx->lexicon('ms3_order_delivery_self'),
                    ':rules' => '{\"first_name\":\"required\",\"last_name\":\"required\", \"email\":\"required|email\"}'
                ]);
            }

            // Проверяем и создаём оплату
            $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}ms3_payments WHERE id = 1");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $modx->prepare("INSERT INTO {$prefix}ms3_payments (id, name, active, position) VALUES (1, :name, 1, 0)");
                $stmt->execute([
                    ':name' => $modx->lexicon('ms3_order_payment_cash')
                ]);
            }

            // Проверяем и создаём связь доставка-оплата
            $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}ms3_delivery_payments WHERE payment_id = 1 AND delivery_id = 1");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $modx->prepare("INSERT INTO {$prefix}ms3_delivery_payments (payment_id, delivery_id) VALUES (1, 1)");
                $stmt->execute();
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
