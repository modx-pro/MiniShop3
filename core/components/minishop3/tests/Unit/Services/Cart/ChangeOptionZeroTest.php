<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Cart;

require_once __DIR__ . '/../../../stubs/ModxStub.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Services\Cart\CartItemManager;
use MiniShop3\Services\Cart\CartMutationHandler;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #862: changeOption must keep 0 / "0" for duplicate-key detection and for
 * persistence, and drop only null / blank strings.
 */
final class ChangeOptionZeroTest extends TestCase
{
    public function testZeroOptionsSurviveChangeOption(): void
    {
        $itemManager = new FakeChangeItemManager(['floor' => 1, 'comment' => 'old']);
        $handler = $this->handler($itemManager);

        $response = $handler->changeOption(
            new \stdClass(),
            $this->createMock(msOrder::class),
            ['old-key' => ['count' => 1]],
            'old-key',
            ['floor' => 0, 'room' => '0', 'comment' => '  ']
        );

        self::assertTrue($response['success']);
        self::assertSame(
            ['floor' => 0, 'room' => '0'],
            $itemManager->mergedForKey
        );
        // The handler must pass the same normalized options to persistence.
        self::assertSame(['old-key', ['floor' => 0, 'room' => '0', 'comment' => '  ']], $itemManager->updateArgs);
    }

    private function handler(FakeChangeItemManager $itemManager): CartMutationHandler
    {
        $modx = new class extends modX {
        };

        return new class (
            $modx,
            $this->createMock(MiniShop3::class),
            $this->createMock(OrderDraftManager::class),
            $itemManager,
            $this->createMock(OrderLogService::class)
        ) extends CartMutationHandler {
            protected function invokeEvent(object $controller, string $eventName, array $params = []): array
            {
                return ['success' => true];
            }

            protected function reloadCart(?msOrder $draft): array
            {
                return [];
            }

            protected function successWithCart(
                object $controller,
                ?msOrder $draft,
                array $cart,
                string $message,
                array $data = [],
                array $placeholders = []
            ): array {
                return ['success' => true, 'data' => $data];
            }
        };
    }
}

final class FakeChangeItemManager extends CartItemManager
{
    /** @var array<string, mixed> */
    public array $mergedForKey = [];
    /** @var array{0: string, 1: array<string, mixed>}|null */
    public ?array $updateArgs = null;

    /** @param array<string, mixed> $options */
    public function __construct(private array $options)
    {
    }

    public function getItemByKey(msOrder $draft, string $productKey): ?msOrderProduct
    {
        return new FakeChangeItem(['options' => $this->options]);
    }

    public function generateProductKey(array $product, array $options = []): string
    {
        $this->mergedForKey = $options;

        return 'new-key';
    }

    public function updateItemOptions(msOrder $draft, string $productKey, array $newOptions): ?string
    {
        $this->updateArgs = [$productKey, $newOptions];

        return 'new-key';
    }
}

final class FakeChangeItem extends msOrderProduct
{
    /** @var array<string, mixed> */
    private array $fields;

    /** @param array<string, mixed> $fields */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function &getOne($alias, $criteria = null, $cacheFlag = true)
    {
        static $product = null;
        $product = new class {
            /** @return array<string, int> */
            public function toArray(): array
            {
                return ['id' => 1];
            }
        };

        return $product;
    }
}
