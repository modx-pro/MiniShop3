<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Services\Order\OrderDraftManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #860: один ключ — одна таблица.
 *
 * У заказа и адреса общие колонки id, createdon, updatedon и properties.
 * updateField() писал значение в обе, из-за чего свойства адреса затирались
 * свойствами заказа — в том числе при обычном выборе сохранённого адреса,
 * где OrderAddressManager передаёт сюда properties заказа.
 */
final class OrderDraftManagerUpdateFieldTargetTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testPropertiesGoToOrderOnly(): void
    {
        $address = $this->makeAddress(['properties' => ['address_own' => 1]]);
        $draft = $this->makeDraft(['properties' => []], $address);

        $this->makeManager()->updateField($draft, 'properties', ['address_hash' => 'abc']);

        self::assertSame(['address_hash' => 'abc'], $draft->get('properties'));
        self::assertSame(['address_own' => 1], $address->get('properties'));
        self::assertFalse($address->saved, 'Address row must not be touched by an order-column write');
    }

    public function testAddressColumnStillGoesToAddress(): void
    {
        $address = $this->makeAddress(['city' => '']);
        $draft = $this->makeDraft(['properties' => []], $address);

        $this->makeManager()->updateField($draft, 'city', 'Томск');

        self::assertSame('Томск', $address->get('city'));
        self::assertNull($draft->get('city'));
    }

    public function testUnknownKeyStillLandsInValidatedProperties(): void
    {
        $address = $this->makeAddress();
        $draft = $this->makeDraft(['properties' => []], $address);

        $this->makeManager()->updateField($draft, 'custom_field', 'value');

        self::assertSame(['_validated' => ['custom_field' => 'value']], $draft->get('properties'));
    }

    private function makeManager(): OrderDraftManager
    {
        return new OrderDraftManager($this->createStub(modX::class), $this->createStub(MiniShop3::class));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeDraft(array $fields, object $address): msOrder
    {
        $columns = [
            'id' => null,
            'user_id' => null,
            'customer_id' => null,
            'token' => null,
            'uuid' => null,
            'createdon' => null,
            'updatedon' => null,
            'num' => null,
            'cost' => null,
            'cart_cost' => null,
            'delivery_cost' => null,
            'weight' => null,
            'status_id' => null,
            'delivery_id' => null,
            'payment_id' => null,
            'context' => null,
            'order_comment' => null,
            'properties' => null,
        ];

        return new class (array_merge($columns, $fields), $address) extends msOrder {
            /** @var array<string, mixed> */
            private array $fields;

            public $Address;

            public bool $saved = false;

            /**
             * @param array<string, mixed> $fields
             */
            public function __construct(array $fields, object $address)
            {
                $this->fields = $fields;
                $this->Address = $address;
            }

            public function get($k)
            {
                return $this->fields[$k] ?? null;
            }

            public function set($k, $v = null, $vType = '')
            {
                $this->fields[$k] = $v;

                return true;
            }

            public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
            {
                return $this->fields;
            }

            public function save($cacheFlag = null)
            {
                $this->saved = true;

                return true;
            }
        };
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeAddress(array $fields = []): object
    {
        $columns = [
            'id' => null,
            'order_id' => null,
            'createdon' => null,
            'updatedon' => null,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'email' => null,
            'country' => null,
            'index' => null,
            'region' => null,
            'city' => null,
            'metro' => null,
            'street' => null,
            'building' => null,
            'entrance' => null,
            'floor' => null,
            'room' => null,
            'comment' => null,
            'text_address' => null,
            'properties' => null,
        ];

        return new class (array_merge($columns, $fields)) extends msOrderAddress {
            /** @var array<string, mixed> */
            private array $fields;

            public bool $saved = false;

            /**
             * @param array<string, mixed> $fields
             */
            public function __construct(array $fields)
            {
                $this->fields = $fields;
            }

            public function get($k)
            {
                return $this->fields[$k] ?? null;
            }

            public function set($k, $v = null, $vType = '')
            {
                $this->fields[$k] = $v;

                return true;
            }

            public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false)
            {
                return $this->fields;
            }

            public function save($cacheFlag = null)
            {
                $this->saved = true;

                return true;
            }
        };
    }
}
