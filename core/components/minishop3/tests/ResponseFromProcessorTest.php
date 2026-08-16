<?php

/**
 * Response::fromProcessor / statusFromProcessorObject (no MODX).
 *
 * Run: php tests/ResponseFromProcessorTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\ApiErrorCode;
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

$withData = Response::fromProcessor(
    $makeProcessor(true, 'import failed', ['import_id' => 'imp-1', 'errors' => 2])
);
if (($withData->getData()['data']['import_id'] ?? null) !== 'imp-1') {
    $fail('fromProcessor error must expose processor object under data');
}
if (($withData->getData()['data']['errors'] ?? null) !== 2) {
    $fail('fromProcessor must keep import error count in data.errors');
}
if (($withData->getData()['errors'] ?? null) !== null) {
    $fail('fromProcessor must not put processor object into validation errors slot');
}

$fieldProcessor = new class {
    public function isError(): bool
    {
        return true;
    }

    public function getMessage(): string
    {
        return '';
    }

    public function getObject(): array
    {
        return [];
    }

    public function getResponse(): array
    {
        return [
            'success' => false,
            'message' => '',
            'errors' => [
                ['id' => 'fields', 'msg' => 'This field is required'],
            ],
            'object' => [],
        ];
    }
};

$fieldError = Response::fromProcessor($fieldProcessor);
if (($fieldError->getData()['errors'][0]['id'] ?? null) !== 'fields') {
    $fail('fromProcessor must preserve MODX field errors under errors');
}
if (($fieldError->getData()['message'] ?? null) !== 'This field is required') {
    $fail('fromProcessor must derive message from field errors when empty');
}

$authThrottle = Response::fromProcessor(
    $makeProcessor(true, 'rate limited', ['code' => HttpStatus::TOO_MANY_REQUESTS])
);
if (array_key_exists('data', $authThrottle->getData())) {
    $fail('auth-only code object must not add empty data key');
}

// Profile/email unauth envelope (AddressController pattern)
$unauth = Response::error('login required', HttpStatus::UNAUTHORIZED);
if ($unauth->getStatusCode() !== HttpStatus::UNAUTHORIZED) {
    $fail('Response::error UNAUTHORIZED status mismatch');
}
if (($unauth->getData()['code'] ?? null) !== HttpStatus::UNAUTHORIZED) {
    $fail('Response::error body must include code 401');
}
if (($unauth->getData()['error_code'] ?? null) !== ApiErrorCode::UNAUTHORIZED) {
    $fail('Response::error must include additive error_code');
}
if (($fieldError->getData()['error_code'] ?? null) !== ApiErrorCode::VALIDATION_FAILED) {
    $fail('fromProcessor field errors must set error_code=validation_failed');
}
if (($throttle->getData()['error_code'] ?? null) !== ApiErrorCode::RATE_LIMITED) {
    $fail('fromProcessor 429 must set error_code=rate_limited');
}

fwrite(STDOUT, "OK ResponseFromProcessorTest\n");
exit(0);
