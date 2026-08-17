<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * ms3 service bag: cart/order/customer facades for Router journey.
 */
final class JourneyMs3
{
    /** @var object Cart facade (JourneyCart or Cart for Phase B) */
    public object $cart;

    public JourneyOrder $order;

    public object $customer;

    public object $utils;

    /** @var array<string, mixed> */
    public array $config = ['ctx' => 'web'];

    public function __construct()
    {
        $cart = new JourneyCart();
        $this->cart = $cart;
        $this->order = new JourneyOrder();
        $this->order->bindCart($cart);

        $this->customer = new class {
            /**
             * @return array{success: bool, message: string, data: array<string, mixed>}
             */
            public function generateToken(): array
            {
                return [
                    'success' => true,
                    'message' => '',
                    'data' => ['token' => 'explicit-guest-token'],
                ];
            }
        };

        $this->utils = new class {
            public function success(string $message = '', array $data = []): array
            {
                return JourneyResult::ok($message, $data);
            }

            public function error(string $message = '', array $data = []): array
            {
                return JourneyResult::fail($message, $data);
            }
        };
    }

    public function initialize(string $ctx = 'web'): bool
    {
        $this->config['ctx'] = $ctx;

        return true;
    }
}
