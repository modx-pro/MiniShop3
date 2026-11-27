<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\EmailVerificationService;
use MiniShop3\Services\Customer\RegisterService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * #898: a missing ms3_customer_require_email_verification row must behave like
 * a fresh install (verification off), not like a forgotten getOption() default.
 */
final class RegisterServiceDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testMissingVerificationSettingDoesNotSendConfirmationEmail(): void
    {
        // Unit environment uses the minimal xPDO stub (get() only), so the
        // customer needs concrete set()/save() for the registration flow.
        $customer = new class extends msCustomer {
            public int $id = 42;

            /** @var array<string, mixed> */
            private array $fields = [];

            public function set($key, $value = null): bool
            {
                $this->fields[$key] = $value;

                return true;
            }

            public function get($key)
            {
                return $this->fields[$key] ?? null;
            }

            public function save($cacheFlag = null): bool
            {
                return true;
            }
        };

        $modx = new class ($customer) extends modX {
            public function __construct(private object $customer)
            {
                parent::__construct();
            }

            /**
             * ModxStub returns the production default for any unset key.
             */
            public function getOption(string $key, $options = null, $default = null)
            {
                return $default;
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                return null;
            }

            public function newObject($className, $fields = null, $cacheFlag = true)
            {
                return $this->customer;
            }
        };

        $verification = $this->createMock(EmailVerificationService::class);
        $verification->expects(self::never())->method('sendVerificationEmail');

        $service = new RegisterService($modx);
        $service->setEmailVerification($verification);

        $result = $service->register([
            'email' => 'no-verify@example.com',
            'password' => 'secret123',
        ]);

        self::assertTrue($result['success']);
        self::assertSame($customer, $result['customer']);
        self::assertNull($result['customer']->get('email_verified_at'));
    }
}
