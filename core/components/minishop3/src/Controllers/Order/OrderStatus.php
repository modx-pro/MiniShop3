<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\Controllers\Payment\Payment;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus as msOrderStatusModel;
use MiniShop3\Model\msCustomer;
use MiniShop3\Notifications\NotificationManager;
use MiniShop3\Notifications\Order\StatusChangedNotification;
use MODX\Revolution\modContextSetting;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

class OrderStatus
{
    /** @var modX */
    public $modx;
    /** @var MiniShop3 */
    public $ms3;
    /** @var OrderLog */
    private $orderLogController;
    /** @var NotificationManager */
    private $notifications;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->orderLogController = new OrderLog($ms3);

        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Get NotificationManager (lazy loading)
     *
     * @return NotificationManager
     */
    protected function getNotificationManager(): NotificationManager
    {
        if (!$this->notifications) {
            $this->notifications = $this->modx->services->get('ms3_notifications');
        }
        return $this->notifications;
    }

    /**
     * Switch order status
     *
     * @param integer $order_id The id of msOrder
     * @param integer $status_id The id of msOrderStatus
     *
     * @return boolean|string
     */
    public function change(int $order_id, int $status_id): bool|string
    {
        /** @var msOrder $order */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $order_id]);
        if (!$msOrder) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }
        $ctx = $msOrder->get('context');
        $this->modx->switchContext($ctx);
        $this->ms3->initialize($ctx);

        /** @var msOrderStatusModel $status */
        $status = $this->modx->getObject(msOrderStatusModel::class, ['id' => $status_id, 'active' => 1]);
        if (!$status) {
            return $this->modx->lexicon('ms3_err_status_nf');
        }
        /** @var msOrderStatusModel $old_status */
        $old_status = $this->modx->getObject(
            msOrderStatusModel::class,
            ['id' => $msOrder->get('status_id'), 'active' => 1]
        );
        if ($old_status) {
            if ($old_status->get('final')) {
                return $this->modx->lexicon('ms3_err_status_final');
            }
            if ($old_status->get('fixed')) {
                if ($status->get('position') <= $old_status->get('position')) {
                    return $this->modx->lexicon('ms3_err_status_fixed');
                }
            }
        }

        if ($msOrder->get('status_id') == $status_id) {
            return $this->modx->lexicon('ms3_err_status_same');
        }

        $eventParams = [
            'msOrder' => $msOrder,
            'old_status' => $old_status->get('id'),
            'status' => $status_id,
        ];
        $response = $this->ms3->utils->invokeEvent('msOnBeforeChangeOrderStatus', $eventParams);
        if (!$response['success']) {
            return $response['message'];
        }

        $msOrder->set('status_id', $status_id);

        if ($msOrder->save()) {
            $this->orderLogController->add($msOrder->get('id'), $status_id, 'status');
            $response = $this->ms3->utils->invokeEvent('msOnChangeOrderStatus', [
                'msOrder' => $msOrder,
                'old_status' => $old_status->get('id'),
                'status' => $status_id,
            ]);
            if (!$response['success']) {
                return $response['message'];
            }

            // Send notifications via NotificationManager
            $this->sendNotifications($msOrder, $status, $old_status);
        }

        return true;
    }

    /**
     * Send notifications for status change
     *
     * Uses NotificationConfigService to determine which channels are enabled
     * for each recipient type. Channel selection is configured in ms3_notification_configs table.
     *
     * @param msOrder $msOrder
     * @param msOrderStatusModel $newStatus
     * @param msOrderStatusModel|null $oldStatus
     * @return void
     */
    protected function sendNotifications(
        msOrder $msOrder,
        msOrderStatusModel $newStatus,
        ?msOrderStatusModel $oldStatus
    ): void {
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] ====== START sendNotifications() ======');
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] Order ID: ' . $msOrder->get('id') . ', New Status ID: ' . $newStatus->get('id'));

        // Prepare language settings
        $lang = $this->getLang($msOrder);
        $this->modx->setOption('cultureKey', $lang);
        $this->modx->lexicon->load($lang . ':minishop3:default', $lang . ':minishop3:cart');

        // Create notification
        $notification = new StatusChangedNotification(
            $this->modx,
            $msOrder,
            $newStatus,
            $oldStatus
        );
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] StatusChangedNotification created');

        $notificationManager = $this->getNotificationManager();
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] NotificationManager obtained: ' . ($notificationManager ? 'YES' : 'NO'));

        // Send to customer (channels determined by notification config)
        $customerRecipient = $this->getCustomerRecipient($msOrder);
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] Customer recipient: ' . json_encode($customerRecipient));
        if ($customerRecipient) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] Calling sendToCustomer()...');
            $notificationManager->sendToCustomer($notification, $customerRecipient);
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] No customer recipient found!');
        }

        // Send to manager(s) (channels determined by notification config)
        $managerRecipients = $this->getManagerRecipients();
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] Manager recipients: ' . json_encode($managerRecipients));
        foreach ($managerRecipients as $managerRecipient) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] Calling sendToManager()...');
            $notificationManager->sendToManager($notification, $managerRecipient);
        }

        $this->modx->log(modX::LOG_LEVEL_ERROR, '[DEBUG Notifications] ====== END sendNotifications() ======');
    }

    /**
     * Get customer recipient data for all notification channels
     *
     * Returns all available contact information for the customer:
     * - email: for EmailChannel
     * - phone: for SmsChannel
     * - telegram_id: for TelegramChannel
     *
     * @param msOrder $msOrder
     * @return array|null Returns null only if no contact info available at all
     */
    protected function getCustomerRecipient(msOrder $msOrder): ?array
    {
        $recipient = [
            'type' => 'customer',
            'email' => null,
            'phone' => null,
            'telegram_id' => null,
        ];

        $hasContact = false;

        // Try to get contact info from msCustomer
        /** @var msCustomer|null $customer */
        $customer = $msOrder->getOne('Customer');
        if ($customer) {
            $recipient['customer'] = $customer->toArray();

            if ($email = $customer->get('email')) {
                $recipient['email'] = $email;
                $hasContact = true;
            }
            if ($phone = $customer->get('phone')) {
                $recipient['phone'] = $phone;
                $hasContact = true;
            }
            // telegram_id may be stored in extended fields
            $extended = $customer->get('extended');
            if (is_array($extended) && !empty($extended['telegram_id'])) {
                $recipient['telegram_id'] = $extended['telegram_id'];
                $hasContact = true;
            }
        }

        // Fallback/supplement from modUserProfile
        $userId = $msOrder->get('user_id');
        if ($userId) {
            /** @var modUserProfile|null $profile */
            $profile = $this->modx->getObject(modUserProfile::class, ['internalKey' => $userId]);
            if ($profile) {
                // Only use profile data if customer data is missing
                if (empty($recipient['email']) && $profile->get('email')) {
                    $recipient['email'] = $profile->get('email');
                    $hasContact = true;
                }
                if (empty($recipient['phone']) && $profile->get('phone')) {
                    $recipient['phone'] = $profile->get('phone');
                    $hasContact = true;
                }
                // Check extended for telegram_id
                $extended = $profile->get('extended');
                if (empty($recipient['telegram_id']) && is_array($extended) && !empty($extended['telegram_id'])) {
                    $recipient['telegram_id'] = $extended['telegram_id'];
                    $hasContact = true;
                }
            }
        }

        return $hasContact ? $recipient : null;
    }

    /**
     * Get manager recipients data for all notification channels
     *
     * Reads from system settings:
     * - ms3_email_manager: comma-separated emails
     * - ms3_phone_manager: comma-separated phones (for SMS)
     * - ms3_telegram_manager: comma-separated telegram chat IDs
     *
     * @return array[]
     */
    protected function getManagerRecipients(): array
    {
        // Get all contact channels for managers
        $emails = $this->parseManagerSetting('ms3_email_manager', $this->modx->getOption('emailsender'));
        $phones = $this->parseManagerSetting('ms3_phone_manager');
        $telegramIds = $this->parseManagerSetting('ms3_telegram_manager');

        // Determine max count to create recipients
        $maxCount = max(count($emails), count($phones), count($telegramIds), 1);

        $recipients = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $recipient = [
                'type' => 'manager',
                'email' => $emails[$i] ?? null,
                'phone' => $phones[$i] ?? null,
                'telegram_id' => $telegramIds[$i] ?? null,
            ];

            // Only add if at least one contact method exists
            if ($recipient['email'] || $recipient['phone'] || $recipient['telegram_id']) {
                $recipients[] = $recipient;
            }
        }

        // If no structured recipients, try to create at least one with available data
        if (empty($recipients)) {
            $recipient = [
                'type' => 'manager',
                'email' => $emails[0] ?? null,
                'phone' => $phones[0] ?? null,
                'telegram_id' => $telegramIds[0] ?? null,
            ];
            if ($recipient['email'] || $recipient['phone'] || $recipient['telegram_id']) {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /**
     * Parse comma-separated manager setting
     *
     * @param string $key Setting key
     * @param string|null $default Default value
     * @return array
     */
    protected function parseManagerSetting(string $key, ?string $default = null): array
    {
        $value = $this->modx->getOption($key, null, $default ?? '');
        if (empty($value)) {
            return [];
        }

        $items = array_map('trim', explode(',', $value));
        return array_filter($items, fn($item) => !empty($item));
    }

    /**
     * Get language for order notifications
     *
     * @param msOrder $msOrder
     * @return string
     */
    protected function getLang($msOrder): string
    {
        $lang = $this->modx->getOption('cultureKey', null, 'en', true);

        // Check user setting
        $tmp = $this->modx->getObject(
            modUserSetting::class,
            ['key' => 'cultureKey', 'user' => $msOrder->get('user_id')]
        );
        if ($tmp) {
            $lang = $tmp->get('value');
        } else {
            // Check context setting
            $tmp = $this->modx->getObject(
                modContextSetting::class,
                ['key' => 'cultureKey', 'context_key' => $msOrder->get('context')]
            );
            if ($tmp) {
                $lang = $tmp->get('value');
            }
        }

        return $lang;
    }

    /**
     * Get payment link for order
     *
     * @param mixed $msPayment
     * @param msOrder $msOrder
     * @return string
     */
    protected function getPaymentLink($msPayment, $msOrder): string
    {
        $class = $msPayment->get('class');
        if (!empty($class)) {
            $this->ms3->loadCustomClasses('payment');
            if (class_exists($class)) {
                /** @var Payment $controller */
                $controller = new $class($msOrder);
                if (method_exists($controller, 'getPaymentLink')) {
                    return $controller->getPaymentLink($msOrder);
                }
            }
        }

        return '';
    }
}
