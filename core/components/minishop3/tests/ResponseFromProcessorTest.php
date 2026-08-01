<?php

/**
 * Response::fromProcessor / statusFromProcessorObject (no MODX).
 *
 * Запуск: php tests/ProcessorErrorHttpStatusTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$makeProcessor = static function (bool $error, string $message, mixed $object): object {
    return new class ($error, $message, $object) {
        public function __construct(
            private bool $error,
            private string $message,
            private mixed $object
        ) {
        }

        public function isError(): bool
        {
            return $this->error;
        }

        public function getMessage(): string
        {
            return $this->message;
        }

        public function getObject(): mixed
        {
            return $this->object;
        }
    };
};

// Login/Register failure objects → HTTP status (issue #414)
$cases = [
    ['object' => ['code' => HttpStatus::UNAUTHORIZED], 'expect' => HttpStatus::UNAUTHORIZED],
    ['object' => ['code' => HttpStatus::TOO_MANY_REQUESTS], 'expect' => HttpStatus::TOO_MANY_REQUESTS],
    ['object' => ['code' => HttpStatus::INTERNAL_SERVER_ERROR], 'expect' => HttpStatus::INTERNAL_SERVER_ERROR],
    ['object' => [], 'expect' => HttpStatus::BAD_REQUEST],
    ['object' => null, 'expect' => HttpStatus::BAD_REQUEST],
    ['object' => '{"code":429}', 'expect' => HttpStatus::TOO_MANY_REQUESTS],
    ['object' => ['code' => 0], 'expect' => HttpStatus::BAD_REQUEST],
    ['object' => ['code' => 999], 'expect' => HttpStatus::BAD_REQUEST],
];

foreach ($cases as $i => $case) {
    $got = Response::statusFromProcessorObject($case['object']);
    if ($got !== $case['expect']) {
        $fail("case {$i}: expected {$case['expect']}, got {$got}");
    }
}

$throttle = Response::fromProcessor(
    $makeProcessor(true, 'rate limited', ['code' => HttpStatus::TOO_MANY_REQUESTS])
);
if ($throttle->getStatusCode() !== HttpStatus::TOO_MANY_REQUESTS) {
    $fail('fromProcessor rate-limit must be HTTP 429');
}
if (($throttle->getData()['code'] ?? null) !== HttpStatus::TOO_MANY_REQUESTS) {
    $fail('fromProcessor body must include code 429');
}

$invalid = Response::fromProcessor(
    $makeProcessor(true, 'bad credentials', ['code' => HttpStatus::UNAUTHORIZED])
);
if ($invalid->getStatusCode() !== HttpStatus::UNAUTHORIZED) {
    $fail('fromProcessor invalid login must be HTTP 401');
}

$ok = Response::fromProcessor($makeProcessor(false, 'ok', ['id' => 1]));
if ($ok->getStatusCode() !== HttpStatus::OK || ($ok->getData()['success'] ?? false) !== true) {
    $fail('fromProcessor success path broken');
}

// Profile/email unauth envelope (AddressController pattern)
$unauth = Response::error('login required', HttpStatus::UNAUTHORIZED);
if ($unauth->getStatusCode() !== HttpStatus::UNAUTHORIZED) {
    $fail('Response::error UNAUTHORIZED status mismatch');
}
if (($unauth->getData()['code'] ?? null) !== HttpStatus::UNAUTHORIZED) {
    $fail('Response::error body must include code 401');
}

fwrite(STDOUT, "OK ResponseFromProcessorTest\n");
exit(0);
