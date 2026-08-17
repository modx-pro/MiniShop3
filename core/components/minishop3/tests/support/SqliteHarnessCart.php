<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Controllers\Cart\Cart;
use MiniShop3\MiniShop3;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Cart\CartMutationHandler;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;

/**
 * Cart facade wired with injected managers for SQLite draft tests.
 */
final class SqliteHarnessCart extends Cart
{
    public function __construct(
        MiniShop3 $ms3,
        CartItemManager $itemManager,
        OrderDraftManager $draftManager
    ) {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->itemManager = $itemManager;
        $this->draftManager = $draftManager;
        $this->mutationHandler = new CartMutationHandler(
            $this->modx,
            $this->ms3,
            $this->draftManager,
            $this->itemManager,
            $this->getOrderLog()
        );
    }

    protected function getOrderLog(): OrderLogService
    {
        return new class extends OrderLogService {
            public function __construct()
            {
            }

            public function addEntry(int $orderId, string $action, array $data, bool $visible = true): bool
            {
                return true;
            }
        };
    }

    protected function getCustomerId(): ?int
    {
        return null;
    }
}
