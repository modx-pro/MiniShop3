<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #864: clean() сохраняет идентичность черновика и пересчитывает суммы.
 *
 * Старый clean() обнулял почти все колонки, включая context, uuid и customer_id.
 * findDraftByToken() фильтрует по context, поэтому после очистки черновик
 * терялся, а uuid (NOT NULL в схеме) получал null.
 *
 * Отдельно закреплено замечание из ревью PR #970: белый список не должен
 * содержать колонок, которых нет в msOrder, а суммы не должны храниться
 * вручную — они выводятся из оставшихся позиций.
 */
final class OrderDraftManagerCleanTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testKeepsDraftIdentity(): void
    {
        $draft = $this->makeDraft();

        self::assertTrue($this->makeManager()->clean($draft));

        foreach (OrderDraftManager::CLEAN_KEEP_FIELDS as $field) {
            self::assertSame($this->seed()[$field], $draft->get($field), "Field {$field} must survive clean()");
        }
    }

    public function testResetsCheckoutState(): void
    {
        $draft = $this->makeDraft();

        self::assertTrue($this->makeManager()->clean($draft));

        foreach (['delivery_id', 'payment_id', 'order_comment', 'properties', 'num', 'total'] as $field) {
            self::assertNull($draft->get($field), "Field {$field} must be reset by clean()");
        }
    }

    public function testClearsAddressButKeepsItsKeys(): void
    {
        $draft = $this->makeDraft();

        self::assertTrue($this->makeManager()->clean($draft));

        $address = $draft->Address;
        self::assertNotNull($address);
        self::assertSame(1, $address->get('id'));
        self::assertSame(42, $address->get('order_id'));
        self::assertNull($address->get('city'));
        self::assertNull($address->get('street'));
        self::assertNull($address->get('phone'));
        self::assertSame(1, $address->saves, 'Cleaned address must be persisted once');
    }

    public function testRecomputesSumsFromRemainingPositions(): void
    {
        $draft = $this->makeDraft();

        self::assertTrue($this->makeManager()->clean($draft));

        self::assertSame(1234.5, $draft->get('cart_cost'), 'cart_cost must be rebuilt from positions');
        self::assertSame(2.5, $draft->get('weight'), 'weight must be rebuilt from positions');
        self::assertSame(1234.5, $draft->get('cost'), 'cost must be cart_cost + reset delivery_cost');
        self::assertSame(0, $draft->get('delivery_cost'), 'delivery was reset, so its cost must be 0');
    }

    public function testKeepsPositionsIntact(): void
    {
        $draft = $this->makeDraft();
        $products = $draft->products;

        self::assertTrue($this->makeManager()->clean($draft));

        self::assertSame($products, $draft->products);
        self::assertCount(2, $draft->products);
    }

    /**
     * Замечание из ревью PR #970: в белом списке были семь колонок, которых в
     * msOrder нет вовсе. Такой список вводит в заблуждение и маскирует
     * настоящие суммовые поля.
     */
    public function testKeepFieldsExistInSchema(): void
    {
        $columns = $this->schemaColumns('msOrder');

        self::assertNotEmpty($columns);

        foreach (OrderDraftManager::CLEAN_KEEP_FIELDS as $field) {
            self::assertContains($field, $columns, "Unknown column {$field} in CLEAN_KEEP_FIELDS");
        }
    }

    /**
     * Суммы не должны возвращаться в белый список: иначе число в колонке
     * разойдётся с позициями.
     */
    public function testSumsAreNotKeptInWhitelist(): void
    {
        foreach (['cost', 'cart_cost', 'delivery_cost', 'weight'] as $field) {
            self::assertNotContains($field, OrderDraftManager::CLEAN_KEEP_FIELDS, $field);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function seed(): array
    {
        return [
            'id' => 77,
            'user_id' => 3,
            'customer_id' => 15,
            'token' => 'a1b2c3d4e5f6',
            'uuid' => '199df9b7-6a3c-4a1e-9a1a-2b7f3c5d8e01',
            'idempotency_key' => 'order-77',
            'createdon' => '2026-11-05 20:00:00',
            'status_id' => 1,
            'context' => 'web',
            'updatedon' => '2026-11-05 20:00:00',
            'num' => 'MS-000077',
            'delivery_id' => 4,
            'payment_id' => 2,
            'order_comment' => 'позвоните за час',
            'properties' => ['_validated' => ['delivery_id' => 4]],
            'cost' => 1469.0,
            'cart_cost' => 1234.5,
            'delivery_cost' => 234.5,
            'weight' => 2.5,
            'total' => 1469.0,
        ];
    }

    private function makeDraft(): CleanTestDraft
    {
        $draft = new CleanTestDraft($this->seed());
        $draft->Address = new CleanTestAddress([
            'id' => 1,
            'order_id' => 42,
            'user_id' => 3,
            'createdon' => '2026-11-05 20:00:00',
            'city' => 'Томск',
            'street' => 'Ленина, 1',
            'phone' => '+7 000 000-00-00',
        ]);
        $draft->products = [
            new CleanTestProduct(['cost' => 1000.0, 'weight' => 0.5, 'count' => 2]),
            new CleanTestProduct(['cost' => 234.5, 'weight' => 1.5, 'count' => 1]),
        ];

        return $draft;
    }

    private function makeManager(): OrderDraftManager
    {
        $modx = $this->createStub(modX::class);
        $orderService = new OrderService($modx);

        $modx->services = new class ($orderService) {
            public function __construct(private readonly OrderService $orderService)
            {
            }

            public function get(string $key): mixed
            {
                return $key === 'ms3_order_service' ? $this->orderService : null;
            }
        };

        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->utils = new class {
            /** @return array{success: bool, message: string, data: array<string, mixed>} */
            public function invokeEvent(string $eventName, array $params = []): array
            {
                return ['success' => true, 'message' => '', 'data' => $params];
            }
        };

        return new OrderDraftManager($modx, $ms3);
    }

    /**
     * @return list<string>
     */
    private function schemaColumns(string $class): array
    {
        $schema = dirname(__DIR__, 4) . '/schema/minishop3.mysql.schema.xml';
        self::assertFileExists($schema);

        $xml = new \SimpleXMLElement((string) file_get_contents($schema));
        $columns = [];

        foreach ($xml->object as $object) {
            if ((string) $object['class'] !== $class) {
                continue;
            }
            // msOrder наследует id от xPDOSimpleObject — в самом объекте его нет.
            if (str_contains((string) $object['extends'], 'xPDOSimpleObject')) {
                $columns[] = 'id';
            }
            foreach ($object->field as $field) {
                $columns[] = (string) $field['key'];
            }
        }

        return $columns;
    }
}

/**
 * msOrder без xPDO map: хранит колонки в массиве, адрес и позиции — отдельно.
 */
final class CleanTestDraft extends msOrder
{
    /** @var array<string, mixed> */
    private array $fields;

    /** @var list<object> */
    public array $products = [];

    public ?CleanTestAddress $Address = null;

    public int $saves = 0;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($key = '', $format = '')
    {
        return $this->fields;
    }

    public function getMany($alias, $criteria = null, $cacheFlag = true)
    {
        return $alias === 'Products' ? $this->products : [];
    }

    public function save($cacheFlag = null)
    {
        $this->saves++;

        return true;
    }
}

/**
 * msOrderAddress-подобный объект: только то, что использует clean().
 */
final class CleanTestAddress
{
    /** @var array<string, mixed> */
    private array $fields;

    public int $saves = 0;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($key = '', $format = '')
    {
        return $this->fields;
    }

    public function save($cacheFlag = null)
    {
        $this->saves++;

        return true;
    }
}

/**
 * Позиция заказа: aggregateProductsTotals() читает cost, weight и count.
 */
final class CleanTestProduct
{
    /** @var array<string, mixed> */
    private array $fields;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
    }

    public function get($key)
    {
        return $this->fields[$key] ?? null;
    }
}
