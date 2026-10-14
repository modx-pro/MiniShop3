<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderFieldManager;
use MiniShop3\Utils\Utils;
use MODX\Revolution\modX;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression #860: витрина не вправе писать служебные колонки черновика.
 *
 * `POST /api/v1/order/add` принимает произвольный key, поэтому посетитель с
 * токеном своей корзины подставлял чужой customer_id — при оформлении он уже
 * не перезаписывается, и заказ уезжал в чужой личный кабинет.
 */
final class OrderFieldManagerProtectedFieldsTest extends TestCase
{
    /**
     * Колонки заказа, которые покупатель заполняет сам. Список продублирован
     * в тесте намеренно: новая колонка в схеме обязана быть осознанно отнесена
     * либо сюда, либо в PROTECTED_ORDER_FIELDS, иначе тест упадёт.
     */
    private const PUBLIC_ORDER_FIELDS = [
        'delivery_id',
        'payment_id',
        'order_comment',
    ];

    private const PUBLIC_ADDRESS_FIELDS = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'country',
        'index',
        'region',
        'city',
        'metro',
        'street',
        'building',
        'entrance',
        'floor',
        'room',
        'comment',
        'text_address',
    ];

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    #[DataProvider('protectedKeys')]
    public function testAddRefusesProtectedField(string $key): void
    {
        $utils = $this->createMock(Utils::class);
        $utils->expects(self::never())->method('invokeEvent');
        $utils->method('error')->willReturnCallback(
            static fn($message = '', $data = []): array => [
                'success' => false,
                'message' => (string) $message,
                'data' => $data,
            ]
        );

        $draftManager = $this->createMock(OrderDraftManager::class);
        $draftManager->expects(self::never())->method('updateField');

        $manager = $this->makeManager($utils, $draftManager);

        $response = $manager->add($this->createStub(msOrder::class), [], $key, 'injected');

        self::assertFalse($response['success'], "Protected field '{$key}' must not be writable");
        self::assertSame('ms3_err_field_protected', $response['message']);
    }

    #[DataProvider('protectedKeys')]
    public function testRemoveRefusesProtectedField(string $key): void
    {
        $utils = $this->createMock(Utils::class);
        $utils->expects(self::never())->method('invokeEvent');

        $draftManager = $this->createMock(OrderDraftManager::class);
        $draftManager->expects(self::never())->method('updateField');

        $manager = $this->makeManager($utils, $draftManager);

        self::assertFalse(
            $manager->remove($this->createStub(msOrder::class), [], $key),
            "Protected field '{$key}' must not be clearable"
        );
    }

    #[DataProvider('checkoutKeys')]
    public function testCheckoutFieldStaysWritable(string $key): void
    {
        self::assertFalse(
            OrderFieldManager::isProtectedField($key),
            "Checkout field '{$key}' must stay writable from the storefront"
        );
    }

    /**
     * Сквозная проверка разрешённого ключа: защита стоит до события, поэтому
     * важно, что обычное поле доходит до валидации и возвращает успех.
     * Ключи с правилами (delivery_id, payment_id) требуют сервисов MODX DI —
     * для них достаточно проверки isProtectedField() выше.
     */
    public function testAddPassesFieldWithoutValidationRule(): void
    {
        $manager = $this->makeManager($this->makePassThroughUtils());

        // draft = null: запись не нужна, проверяем только проход через защиту.
        self::assertTrue($manager->add(null, [], 'city', 'Томск')['success']);
        self::assertTrue($manager->add(null, [], 'custom_field', 'value')['success']);
        self::assertTrue($manager->add(null, [], 'save_address', 1)['success']);
    }

    /**
     * Каждая колонка msOrder и msOrderAddress должна быть классифицирована.
     *
     * @param list<string> $protected
     * @param list<string> $public
     */
    #[DataProvider('schemaCoverage')]
    public function testEverySchemaColumnIsClassified(string $class, array $protected, array $public): void
    {
        $columns = self::schemaColumns($class);
        self::assertNotEmpty($columns, "No columns parsed for {$class}");

        $classified = array_merge($protected, $public);

        foreach ($columns as $column) {
            self::assertContains(
                $column,
                $classified,
                "Column {$class}.{$column} is not classified: add it to OrderFieldManager::PROTECTED_*"
                . ' if the storefront must not write it, or to this test if it is a checkout form field'
            );
        }

        foreach ($public as $column) {
            self::assertFalse(
                OrderFieldManager::isProtectedField($column),
                "Column {$class}.{$column} is listed as public and protected at the same time"
            );
        }
    }

    /**
     * Общие для заказа и адреса колонки закрыты в обоих списках: одним ключом
     * updateField() попадал сразу в две таблицы.
     */
    public function testSharedColumnsAreProtectedOnBothSides(): void
    {
        $shared = array_intersect(
            self::schemaColumns('msOrder'),
            self::schemaColumns('msOrderAddress')
        );

        self::assertNotEmpty($shared);

        foreach ($shared as $column) {
            self::assertContains($column, OrderFieldManager::PROTECTED_ORDER_FIELDS, $column);
            self::assertContains($column, OrderFieldManager::PROTECTED_ADDRESS_FIELDS, $column);
        }
    }

    /**
     * @return list<array{string}>
     */
    public static function protectedKeys(): array
    {
        $keys = array_unique(array_merge(
            OrderFieldManager::PROTECTED_ORDER_FIELDS,
            OrderFieldManager::PROTECTED_ADDRESS_FIELDS
        ));

        return array_map(static fn(string $key): array => [$key], array_values($keys));
    }

    /**
     * @return list<array{string}>
     */
    public static function checkoutKeys(): array
    {
        return array_map(
            static fn(string $key): array => [$key],
            array_merge(self::PUBLIC_ORDER_FIELDS, self::PUBLIC_ADDRESS_FIELDS, ['save_address', 'address_hash'])
        );
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function schemaCoverage(): array
    {
        return [
            'msOrder' => [
                'msOrder',
                OrderFieldManager::PROTECTED_ORDER_FIELDS,
                self::PUBLIC_ORDER_FIELDS,
            ],
            'msOrderAddress' => [
                'msOrderAddress',
                OrderFieldManager::PROTECTED_ADDRESS_FIELDS,
                self::PUBLIC_ADDRESS_FIELDS,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function schemaColumns(string $class): array
    {
        $schema = dirname(__DIR__, 4) . '/schema/minishop3.mysql.schema.xml';
        self::assertFileExists($schema);

        $xml = new \SimpleXMLElement((string) file_get_contents($schema));
        $columns = [];

        foreach ($xml->object as $object) {
            if ((string) $object['class'] !== $class) {
                continue;
            }
            foreach ($object->field as $field) {
                $columns[] = (string) $field['key'];
            }
        }

        return $columns;
    }

    private function makePassThroughUtils(): Utils
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

        return $utils;
    }

    private function makeManager(Utils $utils, ?OrderDraftManager $draftManager = null): OrderFieldManager
    {
        $modx = $this->createStub(modX::class);
        $ms3 = $this->createStub(MiniShop3::class);
        $ms3->utils = $utils;

        return new OrderFieldManager($modx, $ms3, $draftManager ?? $this->createStub(OrderDraftManager::class));
    }
}
