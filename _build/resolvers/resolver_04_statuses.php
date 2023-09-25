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

            $statuses = [
                1 => [
                    'name' => $modx->lexicon('ms3_order_status_draft'),
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
                2 => [
                    'name' => $modx->lexicon('ms3_order_status_new'),
                    'color' => '000000',
                    'email_user' => 1,
                    'email_manager' => 1,
                    'subject_user' => '[[%ms3_email_subject_new_user]]',
                    'subject_manager' => '[[%ms3_email_subject_new_manager]]',
                    'body_user' => 'tpl.msEmail.new.user',
                    'body_manager' => 'tpl.msEmail.new.manager',
                    'final' => 0,
                    'fixed' => 1,
                ],
                3 => [
                    'name' => $modx->lexicon('ms3_order_status_paid'),
                    'color' => '008000',
                    'email_user' => 1,
                    'email_manager' => 1,
                    'subject_user' => '[[%ms3_email_subject_paid_user]]',
                    'subject_manager' => '[[%ms3_email_subject_paid_manager]]',
                    'body_user' => 'tpl.msEmail.paid.user',
                    'body_manager' => 'tpl.msEmail.paid.manager',
                    'final' => 0,
                    'fixed' => 1,
                ],
                4 => [
                    'name' => $modx->lexicon('ms3_order_status_sent'),
                    'color' => '003366',
                    'email_user' => 1,
                    'email_manager' => 0,
                    'subject_user' => '[[%ms3_email_subject_sent_user]]',
                    'subject_manager' => '',
                    'body_user' => 'tpl.msEmail.sent.user',
                    'body_manager' => '',
                    'final' => 1,
                    'fixed' => 1,
                ],
                5 => [
                    'name' => $modx->lexicon('ms3_order_status_cancelled'),
                    'color' => '800000',
                    'email_user' => 1,
                    'email_manager' => 0,
                    'subject_user' => '[[%ms3_email_subject_cancelled_user]]',
                    'subject_manager' => '',
                    'body_user' => 'tpl.msEmail.cancelled.user',
                    'body_manager' => '',
                    'final' => 1,
                    'fixed' => 1,
                ],
            ];

            foreach ($statuses as $id => $properties) {
                if (!$status = $modx->getCount(msOrderStatus::class, ['id' => $id])) {
                    $status = $modx->newObject(
                        msOrderStatus::class,
                        array_merge([
                            'editable' => 0,
                            'active' => 1,
                            'position' => $id - 1,
                        ], $properties)
                    );
                    $status->set('id', $id);
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

                    $status_id = $status->get('id');
                    $status_name = $properties['name'];
                    $key = '';
                    switch ($id) {
                        case '1':
                            $key = 'ms3_status_draft';
                            break;
                        case '2':
                            $key = 'ms3_status_new';
                            break;
                        case '3':
                            $key = 'ms3_status_paid';
                            break;
                    }
                    if (empty($key)) {
                        continue;
                    }

                    $setting = $modx->getObject(modSystemSetting::class, ['key' => $key]);
                    if ($setting) {
                        $value = $setting->get('value');
                        if (empty($value)) {
                            $setting->set('value', $status_id);
                            $setting->save();
                        }
                    }
                }
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            $modx->removeCollection(msOrderStatus::class, []);
            break;
    }
}
return true;
