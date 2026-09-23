<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderStatus as msOrderStatusModel;
use MiniShop3\Model\msCustomer;
use MiniShop3\Notifications\NotificationManager;
use MiniShop3\Notifications\Order\StatusChangedNotification;
use MiniShop3\Services\Events\DomainEvent;
use MiniShop3\Services\Events\DomainEventBridge;
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
 * Flow (contract with #603, see PR #596):
 * 1. Validate + msOnBeforeChangeOrderStatus (may abort before any persist).
 * 2. DB transaction: inventory when enabled, then in-TX lifecycle ports (may deny) → persist status_id → commit.
 *    Rollback is only the DB transaction — no compensating status save after commit.
 * 3. After commit: order log, then msOnChangeOrderStatus, then notifications, then domain
 *    event emit ({@see DomainEvent::orderStatusChanged()} via optional bridge).
 *    Notifications still run if the after-event returns an error — status is already saved.
 *    A failed msOnChangeOrderStatus returns that error and does **not** revert status_id.
 *    Listener and dispatcher failures are logged inside the bridge and do not change this return.
 *
 * Options for {@see change()}:
 * - idempotent=true: already-in-status returns true without events/notify (integrations).
 *   Prefer {@see ensure()} when callers only need "end up in this status".
 */
class OrderStatusService implements OrderStatusChanger
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderLogService $orderLog;
    protected OrderLifecyclePortsInterface $lifecyclePorts;
    protected ?OrderInventoryCoordinator $inventoryCoordinator = null;
    protected ?NotificationManager $notifications = null;
    protected ?DomainEventBridge $domainEvents = null;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderLogService $orderLog,
        ?OrderLifecyclePortsInterface $lifecyclePorts = null,
        ?OrderInventoryCoordinator $inventoryCoordinator = null,
        ?DomainEventBridge $domainEvents = null
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->orderLog = $orderLog;
        $this->lifecyclePorts = $lifecyclePorts ?? new NullOrderLifecyclePorts();
        $this->inventoryCoordinator = $inventoryCoordinator;
        $this->domainEvents = $domainEvents;

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
     * Apply status if the order is not already there. Same-status is success,
     * including when the current status is fixed/final (change() would fail first).
     *
     * @return bool|string True on success, lexicon/error message on failure
     */
    public function ensure(int $orderId, int $statusId, bool $skipNotifications = false): bool|string
    {
        /** @var msOrder|null $msOrder */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if (!$msOrder) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }
        if ((int) $msOrder->get('status_id') === $statusId) {
            return true;
        }

        $result = $this->change($orderId, $statusId, $skipNotifications, ['idempotent' => true]);
        if ($result === true) {
            return true;
        }

        // change() may have committed status_id before msOnChangeOrderStatus failed (#754).
        $fresh = $this->modx->getObject(msOrder::class, ['id' => $orderId]);
        if ($fresh instanceof msOrder && (int) $fresh->get('status_id') === $statusId) {
            return true;
        }

        return $result;
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

        $persistError = $this->persistStatusWithInventory($msOrder, $statusId, $previousStatusId);
        if ($persistError !== null) {
            return $persistError;
        }

        // Log before the after-event: status is already committed, and a plugin
        // error must not erase the history of that transition.
        $this->orderLog->add($msOrder->get('id'), $statusId, 'status');

        $response = $this->ms3->utils->invokeEvent('msOnChangeOrderStatus', [
            'msOrder' => $msOrder,
            'old_status' => $previousStatusId,
            'status' => $statusId,
        ]);
        // Status and inventory already committed (#596). Do not revert status_id.
        // Defer return until after notifications (#754).
        $afterEventError = !$response['success'] ? $response['message'] : null;

        // Send notifications even when after-event failed — status is already committed (#754).
        if (!$skipNotifications) {
            ob_start();
            $this->sendNotifications($msOrder, $status, $oldStatus);
            ob_end_clean();
        }

        if ($this->domainEvents !== null) {
            $this->domainEvents->emit(DomainEvent::orderStatusChanged(
                (int) $msOrder->get('id'),
                (string) $msOrder->get('uuid'),
                $previousStatusId,
                $statusId,
                (float) $msOrder->get('cost'),
                (float) $msOrder->get('cart_cost'),
                (float) $msOrder->get('delivery_cost'),
            ));
        }

        if ($afterEventError !== null) {
            return $afterEventError;
        }

        return true;
    }

    /**
     * Inventory (when enabled), in-TX domain ports, then persist status_id.
     * Joins an already open transaction instead of starting a nested one.
     * On failure rolls back only the transaction this method opened.
     */
    protected function persistStatusWithInventory(
        msOrder $msOrder,
        int $statusId,
        ?int $previousStatusId
    ): ?string {
        $ownsTx = $this->beginOwnedTransaction();

        try {
            $this->inventory()?->applyStatusChange($msOrder, $statusId);

            $portError = $this->runLifecyclePorts($msOrder, $statusId, $previousStatusId);
            if ($portError !== null) {
                $this->rollbackOwnedTransaction($ownsTx);
                $msOrder->set('status_id', $previousStatusId);

                return $portError;
            }

            $msOrder->set('status_id', $statusId);
            if (!$msOrder->save()) {
                $this->rollbackOwnedTransaction($ownsTx);
                $msOrder->set('status_id', $previousStatusId);
                if (!$ownsTx) {
                    $this->inventory()?->compensateUnpersistedChange($msOrder, $statusId);
                }

                return $this->modx->lexicon('ms3_err_unknown');
            }

            $this->commitOwnedTransaction($ownsTx);
        } catch (InventoryException $exception) {
            $this->rollbackOwnedTransaction($ownsTx);
            $msOrder->set('status_id', $previousStatusId);

            return $this->modx->lexicon($exception->getLexiconKey(), $exception->getPlaceholders());
        } catch (\Throwable $e) {
            $this->rollbackOwnedTransaction($ownsTx);
            $msOrder->set('status_id', $previousStatusId);
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[OrderStatusService] persistStatusWithInventory: ' . $e->getMessage()
            );

            return $this->modx->lexicon('ms3_err_unknown');
        }

        return null;
    }

    /**
     * Start a transaction only when none is active.
     * PDO::beginTransaction() throws if one is already open.
     *
     * xPDO exposes the connection as public $pdo and has no inTransaction().
     */
    private function beginOwnedTransaction(): bool
    {
        if (
            !is_callable([$this->modx, 'beginTransaction'])
            || !is_callable([$this->modx, 'commit'])
            || !is_callable([$this->modx, 'rollBack'])
        ) {
            return false;
        }
        if ($this->hasOpenTransaction()) {
            return false;
        }

        $this->modx->beginTransaction();

        return true;
    }

    /**
     * Test doubles may define inTransaction() on the modX subclass.
     * Production xPDO does not: the check is $modx->pdo->inTransaction().
     */
    private function hasOpenTransaction(): bool
    {
        if (method_exists($this->modx, 'inTransaction')) {
            return (bool) $this->modx->inTransaction();
        }

        $pdo = $this->modx->pdo;
        if (!$pdo instanceof \PDO) {
            return false;
        }

        return $pdo->inTransaction();
    }

    private function commitOwnedTransaction(bool $ownsTx): void
    {
        if ($ownsTx) {
            $this->modx->commit();
        }
    }

    private function rollbackOwnedTransaction(bool $ownsTx): void
    {
        if (!$ownsTx) {
            return;
        }
        if (!$this->hasOpenTransaction()) {
            return;
        }
        $this->modx->rollback();
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
     * Invoke in-TX semantic lifecycle ports when the target matches configured status ids.
     * May deny the transition before status_id is persisted (#589–#591 / #603).
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

    protected function inventory(): ?OrderInventoryCoordinator
    {
        return $this->inventoryCoordinator;
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
