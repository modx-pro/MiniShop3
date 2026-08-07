<?php

/**
 * Web auth processors must serialize customer via CustomerPublicDto (#424),
 * plus a behavior test for the DTO serialization itself.
 *
 * Run: php tests/CustomerAuthProcessorsDtoTest.php
 */

declare(strict_types=1);

require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/ModxStub.php';
require __DIR__ . '/stubs/StubMsCustomer.php';

use MiniShop3\MiniShop3;
use MiniShop3\Model\msExtraField;
use MiniShop3\Services\Customer\CustomerPublicDto;
use MiniShop3\Tests\Stubs\StubMsCustomer;
use MODX\Revolution\modX;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$processors = [
    'Login.php',
    'Register.php',
    'VerifyEmail.php',
];

foreach ($processors as $file) {
    $path = __DIR__ . '/../src/Processors/Api/Customer/' . $file;
    $src = file_get_contents($path);
    if ($src === false || $src === '') {
        $fail("unable to read {$file}");
    }

    if (!str_contains($src, 'use MiniShop3\\Services\\Customer\\CustomerPublicDto;')) {
        $fail("{$file} must import CustomerPublicDto");
    }

    if (!str_contains($src, 'CustomerPublicDto::fromCustomer($customer, $this->modx, $ms3)')) {
        $fail("{$file} must return customer via CustomerPublicDto::fromCustomer");
    }

    if (preg_match("/'email_verified'\\s*=>/", $src)) {
        $fail("{$file} must not expose legacy email_verified boolean");
    }

    if (preg_match("/'customer'\\s*=>\\s*\\[/", $src)) {
        $fail("{$file} must not build inline customer array");
    }
}

// Behavior test: fromCustomer() serializes a customer with extra-field allowlist
// and never leaks secret/system columns (#424 review: replace grep-only checks).
$extraKeys = ['loyalty_tier', 'company'];

$modx = new class ($extraKeys) extends modX {
    /** @var list<string> */
    private array $extraKeys;

    /**
     * @param list<string> $extraKeys
     */
    public function __construct(array $extraKeys)
    {
        parent::__construct();
        $this->extraKeys = $extraKeys;
    }

    public function getIterator($className, $criteria = null)
    {
        if ($className !== msExtraField::class) {
            return new ArrayIterator([]);
        }

        $fields = array_map(
            static fn(string $key): object => new class ($key) {
                public function __construct(private string $key)
                {
                }

                public function get(string $k): mixed
                {
                    return $k === 'key' ? $this->key : null;
                }
            },
            $this->extraKeys
        );

        return new ArrayIterator($fields);
    }
};

$ms3 = new class extends MiniShop3 {
    public function __construct()
    {
        // Bypass real constructor: fromCustomer() does not use ms3.
    }
};

$customer = new StubMsCustomer([
    'id' => 42,
    'user_id' => 7,
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
    'phone' => '+10000000000',
    'password' => '$2y$10$notarealhash',
    'token' => 'session-or-api-secret',
    'email_verified_at' => '2026-01-02 03:04:05',
    'is_active' => true,
    'is_blocked' => true,
    'privacy_ip' => '203.0.113.10',
    'failed_login_attempts' => 3,
    'blocked_until' => '2026-01-01 00:00:00',
    'created_at' => '2025-01-01 00:00:00',
    'updated_at' => '2025-06-01 00:00:00',
    'last_login_at' => '2025-12-01 00:00:00',
    'orders_count' => 2,
    'total_spent' => 99.5,
    'last_order_at' => '2025-11-01 00:00:00',
    'privacy_accepted_at' => '2025-01-01 00:00:00',
    'loyalty_tier' => 'gold',
    'company' => 'ACME',
    'future_secret_column' => 'must-not-leak',
]);

$public = CustomerPublicDto::fromCustomer($customer, $modx, $ms3);

$expected = [
    'id' => 42,
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'email' => 'ada@example.com',
    'phone' => '+10000000000',
    'email_verified_at' => '2026-01-02 03:04:05',
    'is_active' => true,
    'created_at' => '2025-01-01 00:00:00',
    'updated_at' => '2025-06-01 00:00:00',
    'last_login_at' => '2025-12-01 00:00:00',
    'orders_count' => 2,
    'total_spent' => 99.5,
    'last_order_at' => '2025-11-01 00:00:00',
    'privacy_accepted_at' => '2025-01-01 00:00:00',
    'loyalty_tier' => 'gold',
    'company' => 'ACME',
];

if ($public !== $expected) {
    $fail(sprintf(
        "fromCustomer behavior mismatch:\nexpected: %s\nactual:   %s",
        json_encode($expected, JSON_UNESCAPED_SLASHES),
        json_encode($public, JSON_UNESCAPED_SLASHES)
    ));
}

foreach (['password', 'token', 'privacy_ip', 'failed_login_attempts', 'blocked_until', 'is_blocked', 'user_id', 'future_secret_column'] as $hidden) {
    if (array_key_exists($hidden, $public)) {
        $fail("fromCustomer leaked secret/system field: {$hidden}");
    }
}

// Empty extra-field registry → only core public fields, no extras leak.
$emptyModx = new class extends modX {
    public function getIterator($className, $criteria = null)
    {
        return new ArrayIterator([]);
    }
};
$barePublic = CustomerPublicDto::fromCustomer($customer, $emptyModx, $ms3);
if (array_key_exists('loyalty_tier', $barePublic) || array_key_exists('company', $barePublic)) {
    $fail('fromCustomer must not expose extra fields when registry has none registered');
}

fwrite(STDOUT, "OK CustomerAuthProcessorsDtoTest\n");
exit(0);
