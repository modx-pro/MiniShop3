<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MODX\Revolution\modX;

/**
 * Order Draft Manager
 *
 * Manages order draft lifecycle: creation, retrieval, and updates.
 * Drafts are orders with status = ms3_status_draft (typically 1).
 */
class OrderDraftManager
{
    protected modX $modx;
    protected MiniShop3 $ms3;

    public function __construct(modX $modx, MiniShop3 $ms3)
    {
        $this->modx = $modx;
        $this->ms3 = $ms3;
    }

    /**
     * Get existing draft order by token
     *
     * Hybrid search strategy:
     * 1. First try to find by token (works for guests and API)
     * 2. If not found and customer_id exists in session - search by customer_id
     * 3. If found by customer_id - sync token to avoid future mismatches
     */
    public function getDraft(string $token, string $ctx = 'web'): ?msOrder
    {
        if (empty($token)) {
            return null;
        }

        $status_draft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        // 1. Try to find by token first (primary method)
        $draft = $this->modx->getObject(msOrder::class, [
            'token' => $token,
            'status_id' => $status_draft,
            'context' => $ctx,
        ]);

        if ($draft) {
            return $draft;
        }

        // 2. Fallback: search by customer_id for authenticated customers
        //    Sort by id DESC to get the most recent draft when multiple exist
        $customerId = (int)($_SESSION['ms3']['customer_id'] ?? 0);
        if ($customerId > 0) {
            $q = $this->modx->newQuery(msOrder::class);
            $q->where([
                'customer_id' => $customerId,
                'status_id' => $status_draft,
                'context' => $ctx,
            ]);
            $q->sortby('id', 'DESC');
            $draft = $this->modx->getObject(msOrder::class, $q);

            if ($draft) {
                // Sync token: update draft token to match current session token
                // This ensures future lookups will find it by token
                $oldToken = $draft->get('token');
                if ($oldToken !== $token) {
                    $draft->set('token', $token);
                    $draft->save();
                    $this->modx->log(
                        modX::LOG_LEVEL_INFO,
                        "[OrderDraftManager] Token synced for customer {$customerId}: draft #{$draft->get('id')}"
                    );
                }
                return $draft;
            }
        }

        return null;
    }

    /**
     * Create new draft order
     */
    public function createDraft(string $token, string $ctx = 'web'): msOrder
    {
        $status_draft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        /** @var msOrder $msOrder */
        $msOrder = $this->modx->newObject(msOrder::class);
        $data = [
            'token' => $token,
            'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'status_id' => $status_draft,
            'context' => $ctx,
            'createdon' => time(),
            'user_id' => $this->modx->getLoginUserID($ctx),
        ];
        $msOrder->fromArray($data);

        // TODO: Event before creating draft
        $saved = $msOrder->save();

        if ($saved) {
            // Create associated address record
            $msOrderAddress = $this->modx->newObject(msOrderAddress::class);
            $msOrderAddress->fromArray([
                'createdon' => time(),
                'order_id' => $msOrder->get('id')
            ]);
            $msOrderAddress->save();
            // TODO: Event after creating draft
        }

        return $msOrder;
    }

    /**
     * Get or create draft order
     *
     * @param string $token Session token
     * @param string $ctx Context key
     * @param int|null $customerId Optional customer ID to link
     * @return msOrder
     */
    public function getOrCreateDraft(string $token, string $ctx = 'web', ?int $customerId = null): msOrder
    {
        $draft = $this->getDraft($token, $ctx);

        if (!$draft) {
            $draft = $this->createDraft($token, $ctx);
        }

        // Link customer if provided and not already linked
        if ($customerId && empty($draft->get('customer_id'))) {
            $draft->set('customer_id', $customerId);
            $draft->save();
        }

        return $draft;
    }

    /**
     * Recalculate order costs (cart, delivery, total)
     */
    public function recalculate(msOrder $draft): void
    {
        // TODO: event before recalculating order
        $products = $draft->getMany('Products');
        $cart_cost = 0;
        $weight = 0;

        if (!empty($products)) {
            foreach ($products as $product) {
                $weight += $product->get('weight');
                $cart_cost += $product->get('cost');
            }
        }

        $delivery_cost = $draft->get('delivery_cost');
        $cost = $cart_cost + $delivery_cost;

        // TODO: event on recalculating order
        $draft->set('updatedon', time());
        $draft->set('cart_cost', $cart_cost);
        $draft->set('cost', $cost);
        $draft->set('weight', $weight);
        $draft->save();
    }

    /**
     * Get order data as array (merged order + address fields)
     */
    public function toArray(?msOrder $draft): array
    {
        if (empty($draft)) {
            $output = $this->modx->getFields(msOrder::class);
            $address = $this->modx->getFields(msOrderAddress::class);
            $addressFields = [];
            foreach ($address as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            return array_merge($output, $addressFields);
        }

        $address = $draft->getOne('Address');
        $output = $draft->toArray();

        if (!empty($address)) {
            $addressFields = [];
            foreach ($address->toArray() as $key => $value) {
                $addressFields['address_' . $key] = $value;
            }
            $output = array_merge($output, $addressFields);
        }

        return $output;
    }

    /**
     * Clean order data (reset all fields to null)
     */
    public function clean(msOrder $draft): bool
    {
        // TODO: Event before clean

        // Clean address fields
        if ($draft->Address) {
            $addressFields = array_keys($draft->Address->toArray());
            foreach ($addressFields as $key) {
                if (!in_array($key, ['id', 'order_id', 'user_id', 'createdon'])) {
                    $draft->Address->set($key, null);
                }
            }
            $draft->Address->save();
        }

        // Clean order fields
        $orderFields = array_keys($draft->toArray());
        foreach ($orderFields as $key) {
            if (!in_array($key, ['id', 'user_id', 'token', 'status_id', 'createdon'])) {
                $draft->set($key, null);
            }
        }

        $draft->set('updatedon', time());
        $draft->save();

        // TODO: event on clean

        return true;
    }

    /**
     * Update draft field
     *
     * @param msOrder $draft Draft order
     * @param string $key Field key
     * @param mixed $value Field value (null to clear)
     * @return bool True if field was updated
     */
    public function updateField(msOrder $draft, string $key, mixed $value = null): bool
    {
        $updated = false;

        $orderFields = array_keys($draft->toArray());
        $addressFields = $draft->Address ? array_keys($draft->Address->toArray()) : [];

        // Update order field
        if (in_array($key, $orderFields)) {
            $draft->set($key, $value);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Update address field
        if (in_array($key, $addressFields)) {
            $draft->Address->set($key, $value);
            $draft->Address->save();
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Handle save_address in properties
        if ($key === 'save_address') {
            $properties = $draft->get('properties') ?? [];
            if (!empty($value)) {
                $properties['save_address'] = 1;
            } else {
                unset($properties['save_address']);
            }
            $draft->set('properties', $properties);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        // Fallback: save non-model fields to properties['_validated']
        if (!$updated) {
            $properties = $draft->get('properties') ?? [];
            $validated = $properties['_validated'] ?? [];

            if ($value === null || $value === '') {
                unset($validated[$key]);
            } else {
                $validated[$key] = $value;
            }

            if (empty($validated)) {
                unset($properties['_validated']);
            } else {
                $properties['_validated'] = $validated;
            }

            $draft->set('properties', $properties);
            $draft->set('updatedon', time());
            $draft->save();
            $updated = true;
        }

        return $updated;
    }

    /**
     * Set delivery cost and recalculate total
     */
    public function setDeliveryCost(msOrder $draft, float $deliveryCost): void
    {
        $cartCost = $draft->get('cart_cost') ?? 0;
        $cost = $cartCost + $deliveryCost;

        $draft->set('delivery_cost', $deliveryCost);
        $draft->set('cost', $cost);
        $draft->save();
    }

    // =========================================================================
    // Methods shared with Cart
    // =========================================================================

    /**
     * Attach customer to draft order
     *
     * @param msOrder $draft Draft order
     * @param int $customerId Customer ID
     * @return bool True if customer was attached
     */
    public function attachCustomer(msOrder $draft, int $customerId): bool
    {
        if ($customerId <= 0) {
            return false;
        }

        if ($draft->get('customer_id') == $customerId) {
            return false; // Already attached
        }

        $draft->set('customer_id', $customerId);
        $draft->set('updatedon', time());
        return $draft->save();
    }

    /**
     * Ensure order has an associated address record
     *
     * @param msOrder $draft Draft order
     * @return msOrderAddress Address object
     */
    public function ensureAddress(msOrder $draft): msOrderAddress
    {
        $address = $draft->getOne('Address');

        if (!$address) {
            $address = $this->modx->newObject(msOrderAddress::class);
            $address->fromArray([
                'order_id' => $draft->get('id'),
                'user_id' => $draft->get('user_id'),
                'createdon' => time(),
            ]);
            $address->save();
        }

        return $address;
    }

    /**
     * Sync draft token with current session token
     *
     * Used when customer logs in and their cart needs to be linked
     * to the new session token.
     *
     * @param msOrder $draft Draft order
     * @param string $newToken New session token
     * @return bool True if token was changed
     */
    public function syncToken(msOrder $draft, string $newToken): bool
    {
        $oldToken = $draft->get('token');

        if ($oldToken === $newToken) {
            return false;
        }

        $draft->set('token', $newToken);
        $saved = $draft->save();

        if ($saved) {
            $this->modx->log(
                modX::LOG_LEVEL_DEBUG,
                "[OrderDraftManager] Token synced: draft #{$draft->get('id')}, {$oldToken} -> {$newToken}"
            );
        }

        return $saved;
    }

    /**
     * Get draft by customer ID (without token)
     *
     * Useful for cart recovery when customer logs in.
     *
     * @param int $customerId Customer ID
     * @param string $ctx Context key
     * @return msOrder|null
     */
    public function getDraftByCustomer(int $customerId, string $ctx = 'web'): ?msOrder
    {
        if ($customerId <= 0) {
            return null;
        }

        $statusDraft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        $q = $this->modx->newQuery(msOrder::class);
        $q->where([
            'customer_id' => $customerId,
            'status_id' => $statusDraft,
            'context' => $ctx,
        ]);
        $q->sortby('id', 'DESC');

        return $this->modx->getObject(msOrder::class, $q);
    }

    /**
     * Delete draft order and all related data
     *
     * @param msOrder $draft Draft order
     * @return bool True if deleted
     */
    public function deleteDraft(msOrder $draft): bool
    {
        // Products are deleted via cascade, but let's be explicit
        $products = $draft->getMany('Products');
        foreach ($products as $product) {
            $product->remove();
        }

        // Address is deleted via cascade
        $address = $draft->getOne('Address');
        if ($address) {
            $address->remove();
        }

        return $draft->remove();
    }

    /**
     * Check if draft has any products
     *
     * @param msOrder $draft Draft order
     * @return bool True if empty
     */
    public function isEmpty(msOrder $draft): bool
    {
        $count = $this->modx->getCount(\MiniShop3\Model\msOrderProduct::class, [
            'order_id' => $draft->get('id'),
        ]);

        return $count === 0;
    }

    /**
     * Get customer ID from session
     *
     * @return int Customer ID or 0
     */
    public function getSessionCustomerId(): int
    {
        return (int)($_SESSION['ms3']['customer_id'] ?? 0);
    }

    /**
     * Bind draft order to customer by token
     *
     * Finds draft with given token and sets customer_id if not already set.
     * Used during login/register to preserve guest cart.
     *
     * @param string $token Session token
     * @param int $customerId Customer ID to bind
     * @param string $ctx Context key
     * @return bool True if draft was found and bound
     */
    public function bindDraftToCustomer(string $token, int $customerId, string $ctx = 'web'): bool
    {
        if (empty($token) || $customerId <= 0) {
            return false;
        }

        $statusDraft = (int)$this->modx->getOption('ms3_status_draft', null, 1) ?: 1;

        $draft = $this->modx->getObject(msOrder::class, [
            'token' => $token,
            'status_id' => $statusDraft,
            'context' => $ctx,
        ]);

        if ($draft && empty($draft->get('customer_id'))) {
            $draft->set('customer_id', $customerId);
            $draft->save();

            $this->modx->log(
                modX::LOG_LEVEL_INFO,
                "[OrderDraftManager] Bound draft #{$draft->get('id')} to customer #{$customerId}"
            );

            return true;
        }

        return false;
    }
}
