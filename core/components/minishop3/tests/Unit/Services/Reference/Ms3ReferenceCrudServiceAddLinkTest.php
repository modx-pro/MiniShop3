<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Reference;

use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Services\Reference\Ms3ReferenceCrudService;
use MiniShop3\Services\Reference\ReferenceResourceConfig;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * addLink() must persist msDeliveryMember composite PK via set(), not fromArray() without setPrimaryKeys.
 */
final class Ms3ReferenceCrudServiceAddLinkTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testAddLinkPersistsCompositePrimaryKeysForDeliveryPayment(): void
    {
        $saved = [];
        $service = new Ms3ReferenceCrudService(
            $this->modxForAddLink($saved),
            ReferenceResourceConfig::forDeliveries()
        );

        $result = $service->addLink(['delivery_id' => 1, 'payment_id' => 42]);

        self::assertTrue($result['success'] ?? false);
        self::assertSame([
            ['delivery_id' => 1, 'payment_id' => 42],
        ], $saved);
    }

    public function testAddLinkPersistsCompositePrimaryKeysForPaymentDelivery(): void
    {
        $saved = [];
        $service = new Ms3ReferenceCrudService(
            $this->modxForAddLink($saved),
            ReferenceResourceConfig::forPayments()
        );

        $result = $service->addLink(['payment_id' => 5, 'delivery_id' => 9]);

        self::assertTrue($result['success'] ?? false);
        self::assertSame([
            ['delivery_id' => 9, 'payment_id' => 5],
        ], $saved);
    }

    public function testAddLinkSaveFailureReturnsError(): void
    {
        $saved = [];
        $service = new Ms3ReferenceCrudService(
            $this->modxForAddLink($saved, false),
            ReferenceResourceConfig::forDeliveries()
        );

        $result = $service->addLink(['delivery_id' => 1, 'payment_id' => 42]);

        self::assertFalse($result['success'] ?? true);
        self::assertSame('Failed to add payment to delivery', $result['message'] ?? null);
        self::assertSame([], $saved);
    }

    /**
     * @param list<array{delivery_id: int, payment_id: int}> $saved
     */
    private function modxForAddLink(array &$saved, bool $saveOk = true): modX
    {
        return new class ($saved, $saveOk) extends modX {
            /**
             * @param list<array{delivery_id: int, payment_id: int}> $saved
             */
            public function __construct(
                private array &$saved,
                private bool $saveOk,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className !== msDeliveryMember::class || !is_array($criteria)) {
                    return null;
                }

                foreach ($this->saved as $row) {
                    if (
                        $row['delivery_id'] === (int) ($criteria['delivery_id'] ?? 0)
                        && $row['payment_id'] === (int) ($criteria['payment_id'] ?? 0)
                    ) {
                        return new \stdClass();
                    }
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                return new DeliveryMemberSaveProbe($this->saved, $this->saveOk);
            }
        };
    }
}

/**
 * Records composite PK fields. fromArray() skips PK keys unless $setPrimaryKeys (xPDO).
 */
final class DeliveryMemberSaveProbe
{
    /** @var array<string, mixed> */
    private array $fields = [];

    /**
     * @param list<array{delivery_id: int, payment_id: int}> $saved
     */
    public function __construct(
        private array &$saved,
        private bool $saveOk,
    ) {
    }

    public function set($key, $value = null): bool
    {
        $this->fields[(string) $key] = $value;

        return true;
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false): bool
    {
        $pk = ['delivery_id', 'payment_id'];
        foreach ((array) $fields as $key => $value) {
            if (!$setPrimaryKeys && in_array((string) $key, $pk, true)) {
                continue;
            }
            $this->fields[(string) $key] = $value;
        }

        return true;
    }

    public function save(): bool
    {
        $row = [
            'delivery_id' => (int) ($this->fields['delivery_id'] ?? 0),
            'payment_id' => (int) ($this->fields['payment_id'] ?? 0),
        ];
        if (!$this->saveOk || $row['delivery_id'] <= 0 || $row['payment_id'] <= 0) {
            return false;
        }

        $this->saved[] = $row;

        return true;
    }
}
