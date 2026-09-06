<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderStatus as msOrderStatusModel;
use MiniShop3\Model\msCustomer;
use MiniShop3\Notifications\NotificationManager;
use MiniShop3\Notifications\Order\StatusChangedNotification;
use MiniShop3\Services\Inventory\InventoryException;
use MiniShop3\Services\Inventory\OrderInventoryCoordinator;
use MODX\Revolution\modContextSetting;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

/**
 * Single gate for non-draft order status changes (issue #592).
 *
 * Do not write `status_id` directly for paid / cancel / sent — call {@see change()}.
 * Draft creation may still set draft status_id without going through this service.
 *
 * After a successful gate: lifecycle ports (#589–#591) → msOnChangeOrderStatus → log → notify.
 * If after-event or a port fails, status is rolled back (no log / notify).
 * msOrderLog is written only after a successful after-event (plugins must not rely on a fresh
 * log row inside msOnChangeOrderStatus).
 *
 * Options for {@see change()}:
 * - idempotent=true: already-in-status returns true without events/notify (integrations).
 */
class OrderStatusService
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderLogService $orderLog;
    protected OrderLifecyclePortsInterface $lifecyclePorts;
    protected ?OrderInventoryCoordinator $inventoryCoordinator = null;
    protected ?NotificationManager $notifications = null;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderLogService $orderLog,
        ?OrderLifecyclePortsInterface $lifecyclePorts = null,
        ?OrderInventoryCoordinator $inventoryCoordinator = null
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->orderLog = $orderLog;
        $this->lifecyclePorts = $lifecyclePorts ?? new NullOrderLifecyclePorts();
        $this->inventoryCoordinator = $inventoryCoordinator;

        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Get NotificationManager (lazy loading)
     */
    protected function getNotificationManager(): NotificationManager
    {
        if ($this->notifications === null) {
            $this->notifications = $this->modx->services->get('ms3_notifications');
        }
        return $this->notifications;
    }

    /**
     * Get list of status IDs from which customer is allowed to cancel order
     *
     * Uses ms3_customer_cancel_allowed_statuses (comma-separated) or defaults to new + paid.
     *
     * @return int[]
     */
    public function getAllowedCancelStatusIds(): array
    {
        $setting = $this->modx->getOption('ms3_customer_cancel_allowed_statuses', null, '');

        if ($setting !== '') {
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $setting))));
            return array_values(array_filter($ids));
        }

        $newId = (int) $this->modx->getOption('ms3_status_new', null, 2);
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);

        return array_filter([$newId, $paidId]);
    }

    /**
     * Switch order status (single gate for non-draft transitions).
     *
     * @param int $orderId The id of msOrder
     * @param int $statusId The id of msOrderStatus
     * @param bool $skipNotifications Skip sending notifications (for admin finalization)
     * @param array{idempotent?: bool} $options idempotent=true → same status is success no-op
     * @return bool|string True on success, error message on failure
     */
    public function change(
        int $orderId,
        int $statusId,
        bool $skipNotifications = false,
        array $options = []
    ): bool|string {
        $idempotent = !empty($options['idempotent']);

        /** @var msOrder|null $msOrder */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$msOrder) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }

        $ctx = $msOrder->get('context');
        $this->modx->switchContext($ctx);
        $this->ms3->initialize($ctx);

        /** @var msOrderStatusModel|null $status */
        $status = $this->modx->getObject(msOrderStatusModel::class, ['id' => $statusId, 'active' => 1]);
        if (!$status) {
            return $this->modx->lexicon('ms3_err_status_nf');
        }

        $storedStatusId = $msOrder->get('status_id');
        $previousStatusId = $storedStatusId !== null ? (int) $storedStatusId : null;

        /** @var msOrderStatusModel|null $oldStatus */
        $oldStatus = $previousStatusId !== null
            ? $this->modx->getObject(msOrderStatusModel::class, ['id' => $previousStatusId])
            : null;

        if ($previousStatusId === $statusId) {
            return $idempotent ? true : $this->modx->lexicon('ms3_err_status_same');
        }

        $transitionError = $this->validateStatusTransition($oldStatus, $status);
        if ($transitionError !== null) {
            return $transitionError;
        }

        $eventParams = [
            'msOrder' => $msOrder,
            'old_status' => $previousStatusId,
            'status' => $statusId,
        ];
        $response = $this->ms3->utils->invokeEvent('msOnBeforeChangeOrderStatus', $eventParams);
        if (!$response['success']) {
            return $response['message'];
        }

        $resolvedStatusId = $statusId;
        $incomingStatus = $response['data']['status'] ?? null;
        if (is_numeric($incomingStatus)) {
            $resolvedStatusId = (int) $incomingStatus;
        }

        if ($resolvedStatusId !== $statusId) {
            $statusId = $resolvedStatusId;
            $status = $this->modx->getObject(msOrderStatusModel::class, ['id' => $statusId, 'active' => 1]);
            if (!$status) {
                return $this->modx->lexicon('ms3_err_status_nf');
            }
            if ($previousStatusId === $statusId) {
                return $idempotent ? true : $this->modx->lexicon('ms3_err_status_same');
            }

            $transitionError = $this->validateStatusTransition($oldStatus, $status);
            if ($transitionError !== null) {
                return $transitionError;
            }
        }

        $persistError = $this->persistStatusWithInventory($msOrder, $statusId);
        if ($persistError !== null) {
            return $persistError;
        }

        $portError = $this->runLifecyclePorts($msOrder, $statusId, $previousStatusId);
        if ($portError !== null) {
            return $this->rollbackStatus($msOrder, $previousStatusId) ?? $portError;
        }

        $response = $this->ms3->utils->invokeEvent('msOnChangeOrderStatus', [
            'msOrder' => $msOrder,
            'old_status' => $previousStatusId,
            'status' => $statusId,
        ]);
        if (!$response['success']) {
            $this->undoUncommittedNewStatus($msOrder, $previousStatusId, $statusId);

            return $this->rollbackStatus($msOrder, $previousStatusId) ?? $response['message'];
        }

        $this->orderLog->add($msOrder->get('id'), $statusId, 'status');

        // Send notifications via NotificationManager (unless skipped)
        // Use output buffering to prevent any stray output from Fenom/pdoTools
        if (!$skipNotifications) {
            ob_start();
            $this->sendNotifications($msOrder, $status, $oldStatus);
            ob_end_clean();
        }

        return true;
    }

    /**
     * Validate transition: final/fixed defaults + optional allow-list (ms3_order_status_transitions).
     */
    protected function validateStatusTransition(
        ?msOrderStatusModel $oldStatus,
        msOrderStatusModel $newStatus
    ): ?string {
        if (!$oldStatus) {
            return null;
        }

        if ($oldStatus->get('final')) {
            return $this->modx->lexicon('ms3_err_status_final');
        }

        if ($oldStatus->get('fixed') && $newStatus->get('position') <= $oldStatus->get('position')) {
            return $this->modx->lexicon('ms3_err_status_fixed');
        }

        $edges = OrderStatusTransitionPolicy::resolve(
            $this->modx->getOption('ms3_order_status_transitions', null, '')
        );
        if ($edges['mode'] === OrderStatusTransitionPolicy::MODE_INVALID) {
            return $this->modx->lexicon('ms3_err_status_transitions_invalid');
        }
        if (
            $edges['mode'] === OrderStatusTransitionPolicy::MODE_ON
            && !isset($edges['edges'][(int) $oldStatus->get('id')][(int) $newStatus->get('id')])
        ) {
            return $this->modx->lexicon('ms3_err_status_transition');
        }

        return null;
    }

    /**
     * Invoke semantic lifecycle ports when the target matches configured status ids.
     */
    protected function runLifecyclePorts(msOrder $order, int $statusId, ?int $previousStatusId): ?string
    {
        $paidId = (int) $this->modx->getOption('ms3_status_paid', null, 3);
        $canceledId = (int) $this->modx->getOption('ms3_status_canceled', null, 5);
        $sentId = (int) $this->modx->getOption('ms3_status_sent', null, 4);

        if ($statusId === $paidId) {
            return $this->lifecyclePorts->onOrderBecamePaid($order, $previousStatusId);
        }
        if ($statusId === $canceledId) {
            return $this->lifecyclePorts->onOrderCancelled($order, $previousStatusId);
        }
        if ($statusId === $sentId) {
            return $this->lifecyclePorts->onOrderShipped($order, $previousStatusId);
        }

        return null;
    }

    /**
     * Apply inventory for the new status, then persist status_id.
     * When inventory is on, both steps share one DB transaction so a failed save cannot leave a hanging reserve.
     */
    protected function persistStatusWithInventory(msOrder $msOrder, int $statusId): ?string
    {
        $inventory = $this->inventory();
        $useTx = $inventory !== null
            && OrderInventoryCoordinator::isInventoryEnabled($this->modx)
            && is_callable([$this->modx, 'beginTransaction']);
        if ($useTx) {
            $this->modx->beginTransaction();
        }

        try {
            $inventory?->applyStatusChange($msOrder, $statusId);
            $msOrder->set('status_id', $statusId);
            if (!$msOrder->save()) {
                if ($useTx) {
                    $this->modx->rollback();
                } else {
                    $inventory?->releaseOrder($msOrder);
                }

                return $this->modx->lexicon('ms3_err_unknown');
            }
            if ($useTx) {
                $this->modx->commit();
            }
        } catch (InventoryException $exception) {
            if ($useTx) {
                $this->modx->rollback();
            }

            return $this->modx->lexicon($exception->getLexiconKey(), $exception->getPlaceholders());
        } catch (\Throwable $exception) {
            if (!$useTx) {
                throw $exception;
            }
            $this->modx->rollback();
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[OrderStatusService] ' . $exception->getMessage());

            return $this->modx->lexicon('ms3_err_unknown');
        }

        return null;
    }

    /**
     * Restore previous status_id after failed after-event or lifecycle port.
     *
     * @return string|null Lexicon/error when rollback persist fails
     */
    protected function rollbackStatus(msOrder $order, ?int $previousStatusId): ?string
    {
        $order->set('status_id', $previousStatusId);
        if ($order->save()) {
            return null;
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            sprintf(
                '[MiniShop3] Failed to rollback order #%s status to %s',
                (string) $order->get('id'),
                $previousStatusId === null ? 'null' : (string) $previousStatusId
            )
        );

        return $this->modx->lexicon('ms3_err_status_rollback');
    }

    protected function inventory(): ?OrderInventoryCoordinator
    {
        return $this->inventoryCoordinator;
    }

    /**
     * After-event failure must not leave New + a live reserve.
     * Paid/canceled persists stay: those inventory operations already finished.
     */
    protected function undoUncommittedNewStatus(msOrder $msOrder, mixed $oldStatusId, int $newStatusId): void
    {
        $inventory = $this->inventory();
        if ($inventory === null || !OrderInventoryCoordinator::isInventoryEnabled($this->modx)) {
            return;
        }
        $newId = (int) $this->modx->getOption('ms3_status_new', null, 2);
        if ($newStatusId !== $newId) {
            return;
        }
        try {
            $inventory->releaseOrder($msOrder);
        } catch (InventoryException $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderStatusService] release after msOnChangeOrderStatus failure: ' . $exception->getMessage()
            );
        }
        $previousId = is_numeric($oldStatusId)
            ? (int) $oldStatusId
            : ((int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1);
        $msOrder->set('status_id', $previousId);
        $msOrder->save();
    }

    /**
     * Send notifications for status change
     *
     * Uses NotificationConfigService to determine which channels are enabled
     * for each recipient type.
     */
    protected function sendNotifications(
        msOrder $msOrder,
        msOrderStatusModel $newStatus,
        ?msOrderStatusModel $oldStatus
    ): void {
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

        $notificationManager = $this->getNotificationManager();

        // Send to customer (channels determined by notification config)
        $customerRecipient = $this->getCustomerRecipient($msOrder);
        if ($customerRecipient) {
            $notificationManager->sendToCustomer($notification, $customerRecipient);
        }

        // Send to manager(s) (channels determined by notification config)
        $managerRecipients = $this->getManagerRecipients();
        foreach ($managerRecipients as $managerRecipient) {
            $notificationManager->sendToManager($notification, $managerRecipient);
        }
    }

    /**
     * Get customer recipient data for all notification channels
     *
     * Contact resolution order (order-scoped first, then fallbacks):
     * 1. msOrderAddress — email/phone from this order's checkout form (also in recipient['address'])
     * 2. msCustomer — fallback email/phone, plus telegram_chat_id and customer payload
     * 3. modUserProfile — last fallback for email/phone/telegram when still empty
     *
     * Resolved email/phone are mirrored into recipient['customer'] when present (for plugins/templates).
     *
     * @return array|null Returns null only if no contact info available
     */
    protected function getCustomerRecipient(msOrder $msOrder): ?array
    {
        $recipient = [
            'type' => 'customer',
            'email' => null,
            'phone' => null,
            'telegram_chat_id' => null,
        ];

        $hasContact = false;

        /** @var msOrderAddress|null $address */
        $address = $msOrder->getOne('Address');
        if ($address) {
            $recipient['address'] = $address->toArray();

            if ($email = $address->get('email')) {
                $recipient['email'] = $email;
                $hasContact = true;
            }
            if ($phone = $address->get('phone')) {
                $recipient['phone'] = $phone;
                $hasContact = true;
            }
        }

        /** @var msCustomer|null $customer */
        $customer = $msOrder->getOne('Customer');
        if ($customer) {
            $recipient['customer'] = $customer->toArray();

            if (empty($recipient['email']) && ($email = $customer->get('email'))) {
                $recipient['email'] = $email;
                $hasContact = true;
            }
            if (empty($recipient['phone']) && ($phone = $customer->get('phone'))) {
                $recipient['phone'] = $phone;
                $hasContact = true;
            }
            $extended = $customer->get('extended');
            if (is_array($extended) && !empty($extended['telegram_chat_id'])) {
                $recipient['telegram_chat_id'] = $extended['telegram_chat_id'];
                $hasContact = true;
            }
        }

        // Fallback from modUserProfile when order address / customer data is missing
        $userId = $msOrder->get('user_id');
        if ($userId) {
            /** @var modUserProfile|null $profile */
            $profile = $this->modx->getObject(modUserProfile::class, ['internalKey' => $userId]);
            if ($profile) {
                if (empty($recipient['email']) && $profile->get('email')) {
                    $recipient['email'] = $profile->get('email');
                    $hasContact = true;
                }
                if (empty($recipient['phone']) && $profile->get('phone')) {
                    $recipient['phone'] = $profile->get('phone');
                    $hasContact = true;
                }
                // Check extended for telegram_chat_id
                $extended = $profile->get('extended');
                if (empty($recipient['telegram_chat_id']) && is_array($extended) && !empty($extended['telegram_chat_id'])) {
                    $recipient['telegram_chat_id'] = $extended['telegram_chat_id'];
                    $hasContact = true;
                }
            }
        }

        if (!empty($recipient['customer']) && is_array($recipient['customer'])) {
            if (!empty($recipient['email'])) {
                $recipient['customer']['email'] = $recipient['email'];
            }
            if (!empty($recipient['phone'])) {
                $recipient['customer']['phone'] = $recipient['phone'];
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
                'telegram_chat_id' => $telegramIds[$i] ?? null,
            ];

            // Only add if at least one contact method exists
            if ($recipient['email'] || $recipient['phone'] || $recipient['telegram_chat_id']) {
                $recipients[] = $recipient;
            }
        }

        // If no structured recipients, try to create at least one with available data
        if (empty($recipients)) {
            $recipient = [
                'type' => 'manager',
                'email' => $emails[0] ?? null,
                'phone' => $phones[0] ?? null,
                'telegram_chat_id' => $telegramIds[0] ?? null,
            ];
            if ($recipient['email'] || $recipient['phone'] || $recipient['telegram_chat_id']) {
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /**
     * Parse comma-separated manager setting
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
     */
    protected function getLang(msOrder $msOrder): string
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
}
