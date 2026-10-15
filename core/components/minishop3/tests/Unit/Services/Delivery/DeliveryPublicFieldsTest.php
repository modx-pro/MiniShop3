<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Delivery;

use MiniShop3\Services\Delivery\DeliveryPublicFields;
use PHPUnit\Framework\TestCase;

/**
 * Regression #889: в шаблон уходят только публичные поля способа доставки.
 *
 * properties держат ключи интеграций (webhook и прочее), class и
 * validation_rules — внутреннее устройство.
 */
final class DeliveryPublicFieldsTest extends TestCase
{
    public function testOnlyAllowlistedFieldsComeOut(): void
    {
        $entity = $this->makeEntity([
            'id' => '7',
            'name' => 'ms3_delivery_self_pickup',
            'description' => 'Самовывоз',
            'price' => 0,
            'logo' => 'logo.png',
            'class' => 'MiniShop3\\Custom\\Delivery',
            'properties' => ['webhook_key' => 'SECRET'],
            'validation_rules' => '{"city":"required"}',
            'weight_price' => 10,
            'free_delivery_amount' => 5000,
        ]);

        $result = DeliveryPublicFields::fromEntity($entity);

        self::assertNotNull($result);
        self::assertSame(['id', 'name', 'description', 'price', 'logo'], array_keys($result));
        self::assertSame(7, $result['id']);
        self::assertSame('ms3_delivery_self_pickup', $result['name']);
        self::assertSame('Самовывоз', $result['description']);
        self::assertSame('logo.png', $result['logo']);
    }

    public function testSecretsNeverLeak(): void
    {
        $entity = $this->makeEntity([
            'id' => 1,
            'name' => 'Курьер',
            'description' => '',
            'price' => 300,
            'logo' => '',
            'properties' => ['webhook_key' => 'SECRET-WEBHOOK-KEY'],
            'class' => 'Some\\Class',
            'validation_rules' => '{"phone":"required"}',
        ]);

        $flat = json_encode(DeliveryPublicFields::fromEntityOrEmpty($entity), JSON_UNESCAPED_UNICODE);

        self::assertIsString($flat);
        self::assertStringNotContainsString('SECRET-WEBHOOK-KEY', $flat);
        self::assertStringNotContainsString('webhook_key', $flat);
        self::assertStringNotContainsString('validation_rules', $flat);
        self::assertStringNotContainsString('Some\\Class', $flat);
    }

    public function testNonEntityGivesNullAndEmptyArray(): void
    {
        self::assertNull(DeliveryPublicFields::fromEntity(null));
        self::assertNull(DeliveryPublicFields::fromEntity('msDelivery'));
        self::assertSame([], DeliveryPublicFields::fromEntityOrEmpty(null));
        self::assertSame([], DeliveryPublicFields::fromEntityOrEmpty(42));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function makeEntity(array $fields): object
    {
        return new class ($fields) {
            /** @var array<string, mixed> */
            private array $fields;

            /**
             * @param array<string, mixed> $fields
             */
            public function __construct(array $fields)
            {
                $this->fields = $fields;
            }

            public function get(string $key): mixed
            {
                return $this->fields[$key] ?? null;
            }
        };
    }
}
