<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * In-memory order facade for Phase A Web API journey.
 *
 * Fixture IDs: delivery 1 ↔ payment 10 linked; payment 99 is not linked (#374).
 */
final class JourneyOrder
{
    public const FIXTURE_DELIVERY_ID = 1;
    public const FIXTURE_PAYMENT_ID = 10;
    public const UNLINKED_PAYMENT_ID = 99;

    private string $token = '';

    /** @var array<string, mixed> */
    private array $fields = [];

    private ?JourneyCart $cart = null;

    private int $submittedOrderId = 0;

    public function bindCart(JourneyCart $cart): void
    {
        $this->cart = $cart;
    }

    public function initialize(string $token): bool
    {
        $this->token = $token;

        return $token !== '';
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function get(): array
    {
        return JourneyResult::ok('', ['order' => $this->fields]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function add(string $key, mixed $value): array
    {
        if ($key === 'payment_id' && !$this->paymentAllowed((int) $value)) {
            return JourneyResult::fail('ms3_err_payment_not_linked');
        }

        $this->fields[$key] = $value;

        return JourneyResult::ok('ms3_order_add_success', ['order' => $this->fields]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function set(array $fields): array
    {
        if (isset($fields['payment_id']) && !$this->paymentAllowed((int) $fields['payment_id'])) {
            return JourneyResult::fail('ms3_err_payment_not_linked');
        }

        $this->fields = array_merge($this->fields, $fields);

        return JourneyResult::ok('ms3_order_set_success', ['order' => $this->fields]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function submit(array $data = []): array
    {
        unset($data);

        if ($this->cart === null || $this->cart->isEmpty($this->token)) {
            return JourneyResult::fail('ms3_err_cart_empty');
        }

        if (empty($this->fields['delivery_id']) || empty($this->fields['payment_id'])) {
            return JourneyResult::fail('ms3_err_order_incomplete');
        }

        $this->submittedOrderId = 501;
        $this->cart->initialize('web', $this->token);
        $this->cart->clean();

        return JourneyResult::ok('ms3_order_submit_success', [
            'msorder' => $this->submittedOrderId,
            'redirect' => '/checkout/success?order=501',
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function getCost(bool $withCart = false): array
    {
        unset($withCart);
        $cartCost = 0.0;
        if ($this->cart !== null) {
            $this->cart->initialize('web', $this->token);
            $status = $this->cart->get()['data']['status'];
            $cartCost = (float) $status['total_cost'];
        }

        $deliveryCost = isset($this->fields['delivery_id']) ? 50.0 : 0.0;
        $paymentCost = isset($this->fields['payment_id']) ? 0.0 : 0.0;

        return JourneyResult::ok('', [
            'cart_cost' => $cartCost,
            'delivery_cost' => $deliveryCost,
            'payment_cost' => $paymentCost,
            'cost' => $cartCost + $deliveryCost + $paymentCost,
        ]);
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function setCustomerAddress(?string $addressHash = null): array
    {
        if ($addressHash === null || $addressHash === '') {
            return JourneyResult::fail('ms3_err_address_hash_required');
        }

        $this->fields['address_hash'] = $addressHash;

        return JourneyResult::ok('ms3_order_address_set_success', [
            'order' => $this->fields,
        ]);
    }

    public function lastSubmittedOrderId(): int
    {
        return $this->submittedOrderId;
    }

    private function paymentAllowed(int $paymentId): bool
    {
        $deliveryId = (int) ($this->fields['delivery_id'] ?? 0);
        if ($deliveryId === self::FIXTURE_DELIVERY_ID && $paymentId === self::FIXTURE_PAYMENT_ID) {
            return true;
        }

        return false;
    }
}
