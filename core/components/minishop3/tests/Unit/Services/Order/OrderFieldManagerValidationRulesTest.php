<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFieldManager;
use MiniShop3\Services\Validation\ValidationService;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #949: правила способа доставки схлопывались через array_unique().
 *
 * Набор правил — карта «поле => строка правил», а array_unique() сравнивает
 * значения. Из нескольких полей с одинаковым 'required' выживало только первое,
 * остальные проверялись лишь на обязательность при оформлении, без формата.
 */
final class OrderFieldManagerValidationRulesTest extends TestCase
{
    /**
     * Набор из стандартного сценария «Почтовая доставка».
     */
    private const DELIVERY_RULES = [
        'receiver' => 'required',
        'phone' => 'required|numeric|min:10',
        'email' => 'required|email',
        'index' => 'required|numeric',
        'city' => 'required',
        'street' => 'required',
    ];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    /**
     * Каждое поле доставки должно остаться в карте правил, даже если строка
     * правил совпадает с чужой.
     */
    public function testDeliveryRulesAreNotDeduplicatedByValue(): void
    {
        $manager = $this->makeManager();

        // Ключ без правил: валидация проходит, но карта уже собрана.
        self::assertTrue($manager->validate(['delivery_id' => 1], 'custom_field', 'value')['success']);

        $rules = $this->readValidationRules($manager);

        foreach (self::DELIVERY_RULES as $field => $rule) {
            self::assertArrayHasKey($field, $rules, "Field '{$field}' lost its validation rules");
            self::assertSame($rule, $rules[$field], "Field '{$field}' has unexpected rules");
        }

        self::assertSame('required|numeric', $rules['payment_id']);
        self::assertSame('required|numeric', $rules['delivery_id']);
    }

    /**
     * Поле с собственным форматом должно проверяться по формату, а не только
     * на непустое значение.
     */
    #[DataProvider('invalidDeliveryValues')]
    public function testDeliveryFieldRuleIsApplied(string $key, mixed $value): void
    {
        $manager = $this->makeManager();

        $response = $manager->validate(['delivery_id' => 1], $key, $value);

        self::assertFalse($response['success'], "Field '{$key}' accepted an invalid value");
        self::assertArrayHasKey($key, $response['data']['error']);
    }

    #[DataProvider('validDeliveryValues')]
    public function testDeliveryFieldRuleAcceptsValidValue(string $key, mixed $value): void
    {
        $manager = $this->makeManager();

        $response = $manager->validate(['delivery_id' => 1], $key, $value);

        self::assertTrue($response['success'], "Field '{$key}' rejected a valid value");
        self::assertSame($value, $response['data']['value']);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidDeliveryValues(): array
    {
        return [
            'receiver empty' => ['receiver', ''],
            'phone not numeric' => ['phone', 'not-a-number'],
            'email malformed' => ['email', 'not-an-email'],
            'index not numeric' => ['index', 'abc'],
            'city empty' => ['city', ''],
            'street empty' => ['street', ''],
        ];
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function validDeliveryValues(): array
    {
        return [
            'receiver' => ['receiver', 'Иван'],
            'phone' => ['phone', '1234567890'],
            'email' => ['email', 'buyer@example.com'],
            'index' => ['index', '634050'],
            'city' => ['city', 'Томск'],
            'street' => ['street', 'Ленина'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function readValidationRules(OrderFieldManager $manager): array
    {
        $property = new \ReflectionProperty(OrderFieldManager::class, 'validationRules');

        return $property->getValue($manager);
    }

    private function makeManager(): OrderFieldManager
    {
        $utils = $this->createStub(Utils::class);
        $utils->method('invokeEvent')->willReturnCallback(
            static fn($name, array $params = []): array => [
                'success' => true,
                'message' => '',
                'data' => $params,
            ]
        );
        $utils->method('success')->willReturnCallback(
            static fn($message = '', $data = []): array => [
                'success' => true,
                'message' => (string) $message,
                'data' => $data,
            ]
        );
        $utils->method('error')->willReturnCallback(
            static fn($message = '', $data = []): array => [
                'success' => false,
                'message' => (string) $message,
                'data' => $data,
            ]
        );

        $modx = $this->createStub(modX::class);
        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->utils = $utils;

        return new class ($modx, $ms3, $this->createStub(OrderDraftManager::class)) extends OrderFieldManager {
            protected function getValidationService(): ValidationService
            {
                return new ValidationService();
            }

            public function getDeliveryValidationRules(int $deliveryId): array
            {
                return [
                    'success' => true,
                    'message' => '',
                    'data' => ['validation_rules' => OrderFieldManagerValidationRulesTest::deliveryRules()],
                ];
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public static function deliveryRules(): array
    {
        return self::DELIVERY_RULES;
    }
}
