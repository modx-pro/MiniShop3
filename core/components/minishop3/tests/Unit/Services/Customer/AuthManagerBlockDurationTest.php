<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\AuthManager;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #886: фоллбэк ms3_customer_block_duration должен совпадать с поставкой.
 *
 * Настройка в _build/elements/settings.php равна 300 секундам, лексикон обещает
 * те же 5 минут. Код подставлял 3600, поэтому без строки настройки покупатель
 * блокировался на час.
 */
final class AuthManagerBlockDurationTest extends TestCase
{
    /** Путь от корня репозитория. */
    private const SETTINGS_FILE = '/_build/elements/settings.php';

    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testShippedBlockDurationIsFiveMinutes(): void
    {
        self::assertSame(
            300,
            $this->shippedBlockDuration(),
            'settings.php must keep the documented 5 minute default'
        );
    }

    public function testMissingSettingFallsBackToShippedDuration(): void
    {
        $customer = $this->customer();

        $this->authManager($this->modx())->handleFailedLogin($customer);

        self::assertEqualsWithDelta(
            time() + $this->shippedBlockDuration(),
            $this->blockedUntil($customer),
            5,
            'missing ms3_customer_block_duration must fall back to the shipped value, not an hour'
        );
    }

    public function testConfiguredDurationWins(): void
    {
        $customer = $this->customer();

        $this->authManager($this->modx(['ms3_customer_block_duration' => 1800]))->handleFailedLogin($customer);

        self::assertEqualsWithDelta(1800, $this->blockedUntil($customer) - time(), 5);
    }

    private function blockedUntil(FakeBlockCustomer $customer): int
    {
        $blockedUntil = strtotime((string) $customer->get('blocked_until'));
        self::assertIsInt($blockedUntil, 'customer must be blocked with a parsable blocked_until');

        return $blockedUntil;
    }

    private function shippedBlockDuration(): int
    {
        $settings = require dirname(__DIR__, 7) . self::SETTINGS_FILE;

        return (int) $settings['ms3_customer_block_duration']['value'];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function modx(array $options = []): modX
    {
        return new class ($options) extends modX {
            /** @param array<string, mixed> $options */
            public function __construct(private array $options)
            {
                parent::__construct();
                // Первая же неудачная попытка должна блокировать покупателя.
                $this->optionOverrides = ['ms3_customer_max_login_attempts' => 1] + $options;
            }
        };
    }

    private function authManager(modX $modx): AuthManager
    {
        return new AuthManager($modx);
    }

    private function customer(): FakeBlockCustomer
    {
        return new FakeBlockCustomer([
            'id' => 42,
            'is_active' => 1,
            'is_blocked' => 0,
            'blocked_until' => null,
            'failed_login_attempts' => 0,
        ]);
    }
}

final class FakeBlockCustomer extends msCustomer
{
    public $id;

    /** @var array<string, mixed> */
    private array $fields;

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields)
    {
        $this->fields = $fields;
        $this->id = (int) ($fields['id'] ?? 0);
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->fields[$k] ?? null;
    }

    public function set($key, $value)
    {
        $this->fields[$key] = $value;

        return true;
    }

    public function save($cacheFlag = null)
    {
        return true;
    }
}
