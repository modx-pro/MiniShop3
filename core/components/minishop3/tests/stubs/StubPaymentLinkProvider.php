<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MiniShop3\Controllers\Payment\Payment;
use MiniShop3\Model\msOrder;

final class StubPaymentLinkProvider extends Payment
{
    public const PAYMENT_LINK = 'https://pay.example/test-order';

    public function send(msOrder $order): array
    {
        return [
            'success' => true,
            'message' => '',
            'data' => ['payment_link' => self::PAYMENT_LINK],
        ];
    }

    public function receive(msOrder $order): array
    {
        return [
            'success' => true,
            'message' => '',
            'data' => [],
        ];
    }
}
