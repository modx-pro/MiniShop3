<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\RegisterService;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class CustomerRegisterLiveTest extends ExtraTestCase
{
    public function testRegisterServiceCreatesCustomerWhenVerificationIsOff(): void
    {
        $this->setSetting('ms3_customer_require_email_verification', '0');
        $this->setSetting('ms3_customer_send_welcome_email', '0');
        $this->modx->lexicon->load('minishop3:default', 'minishop3:customer');

        $email = 'tb-reg-' . bin2hex(random_bytes(4)) . '@example.invalid';
        /** @var RegisterService $register */
        $register = $this->modx->services->get('ms3_register_service');
        $result = $register->register([
            'email' => $email,
            'password' => 'Secret123!',
            'first_name' => 'TB',
            'last_name' => 'User',
            'privacy_accepted' => true,
        ]);

        self::assertTrue($result['success'], $result['message'] ?? 'register failed');
        self::assertInstanceOf(msCustomer::class, $result['customer'] ?? null);
        $this->assertObjectExists(msCustomer::class, ['email' => $email]);
    }

    public function testRegisterServiceRejectsInvalidEmail(): void
    {
        $this->modx->lexicon->load('minishop3:default', 'minishop3:customer');
        /** @var RegisterService $register */
        $register = $this->modx->services->get('ms3_register_service');
        $result = $register->register([
            'email' => 'not-an-email',
            'password' => 'Secret123!',
        ]);

        self::assertFalse($result['success']);
        $this->assertObjectMissing(msCustomer::class, ['email' => 'not-an-email']);
    }
}
