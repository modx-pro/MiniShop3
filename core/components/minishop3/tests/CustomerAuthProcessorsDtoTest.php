<?php

/**
 * Web auth processors must serialize customer via CustomerPublicDto (#424).
 *
 * Run: php tests/CustomerAuthProcessorsDtoTest.php
 */

declare(strict_types=1);

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

fwrite(STDOUT, "OK CustomerAuthProcessorsDtoTest\n");
exit(0);
