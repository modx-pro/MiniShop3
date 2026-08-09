<?php

/**
 * resolveForOrder() with stub payment handler (without MODX bootstrap).
 *
 * Run: php tests/PaymentLinkResolverResolveTest.php
 */

declare(strict_types=1);

namespace xPDO\Om {
    if (!class_exists(xPDOSimpleObject::class, false)) {
        class xPDOSimpleObject
        {
            public function get($k, $format = null, $formatType = '')
            {
                return null;
            }

            public function getOne($alias = null, $criteria = null, $cacheFlag = true)
            {
                return null;
            }
        }
    }
}

namespace {
    require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Controllers\Payment\PaymentProviderInterface;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPayment;
use MiniShop3\Services\Payment\PaymentLinkResolver;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$makeOrder = static function (?object $payment): msOrder {
    return new class($payment) extends msOrder {
        public function __construct(private ?object $payment)
        {
        }

        public function get($k, $format = null, $formatType = '')
        {
            if ($k === 'status_id') {
                return 2;
            }

            return null;
        }

        public function getOne($alias = null, $criteria = null, $cacheFlag = true)
        {
            return $alias === 'Payment' ? $this->payment : null;
        }
    };
};

$makeStatus = static function (int $id, bool $final): msOrderStatus {
    return new class($id, $final) extends msOrderStatus {
        public function __construct(private int $statusId, private bool $isFinal)
        {
        }

        public function get($k, $format = null, $formatType = '')
        {
            return match ($k) {
                'id' => $this->statusId,
                'final' => $this->isFinal ? 1 : 0,
                default => null,
            };
        }
    };
};

$makePayment = static function (): object {
    return new class() {
        public function get($k, $format = null, $formatType = '')
        {
            return $k === 'class' ? StubPaymentHandler::class : null;
        }
    };
};

$makeHandler = static function (?string $link): PaymentProviderInterface {
    return new class($link) implements PaymentProviderInterface {
        public function __construct(private ?string $link)
        {
        }

        public function send(msOrder $order): array
        {
            return ['success' => true, 'data' => ['payment_link' => $this->link]];
        }

        public function receive(msOrder $order): array
        {
            return ['success' => true];
        }

        public function getCost(msOrder $order, msPayment $payment, float $cost): float
        {
            return 0.0;
        }

        public function getOrderHash(msOrder $order): string
        {
            return 'hash';
        }

        public function getPaymentLink(msOrder $order): ?string
        {
            return $this->link;
        }
    };
};

$modx = new class() {
    public function getOption($key, $options = null, $default = null, $skipEmpty = false)
    {
        return match ($key) {
            'ms3_status_paid' => 3,
            'ms3_status_new' => 2,
            default => $default,
        };
    }
};

$paymentService = new class($makeHandler('https://pay.example/order-1')) {
    public function __construct(private PaymentProviderInterface $handler)
    {
    }

    public function loadPaymentHandler(object $payment): ?PaymentProviderInterface
    {
        return $this->handler;
    }
};

$resolver = new PaymentLinkResolver($modx, $paymentService);
$order = $makeOrder($makePayment());
$status = $makeStatus(2, false);

$assertSame(
    'https://pay.example/order-1',
    $resolver->resolveForOrder($order, $status, [2]),
    'eligible status returns payment link'
);

$assertSame(
    null,
    $resolver->resolveForOrder($order, $makeStatus(3, false), [2]),
    'paid status id blocked even if in payStatus list mistake'
);

$assertSame(
    null,
    $resolver->resolveForOrder($order, $makeStatus(2, true), [2]),
    'final status blocked'
);

$assertSame(
    null,
    $resolver->resolveForOrder($makeOrder(null), $status, [2]),
    'missing payment returns null'
);

$emptyHandlerService = new class($makeHandler('')) {
    public function __construct(private PaymentProviderInterface $handler)
    {
    }

    public function loadPaymentHandler(object $payment): ?PaymentProviderInterface
    {
        return $this->handler;
    }
};

$emptyResolver = new PaymentLinkResolver($modx, $emptyHandlerService);
$assertSame(
    null,
    $emptyResolver->resolveForOrder($order, $status, [2]),
    'empty link normalized to null'
);

fwrite(STDOUT, "OK PaymentLinkResolverResolveTest\n");
exit(0);

final class StubPaymentHandler implements PaymentProviderInterface
{
    public function send(msOrder $order): array
    {
        return ['success' => true];
    }

    public function receive(msOrder $order): array
    {
        return ['success' => true];
    }

    public function getCost(msOrder $order, msPayment $payment, float $cost): float
    {
        return 0.0;
    }

    public function getOrderHash(msOrder $order): string
    {
        return 'hash';
    }
}

} // namespace
