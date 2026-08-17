<?php

/**
 * Web API error envelope contract (#572).
 *
 * Run: php tests/WebApiErrorContractTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\DomainMs2Response;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// Address create pattern: Response object keeps HTTP 201
$created = Response::success(['id' => 1], 'created', HttpStatus::CREATED);
if ($created->getStatusCode() !== HttpStatus::CREATED) {
    $fail('create address must preserve HTTP 201 on Response object');
}
if (($created->getData()['success'] ?? false) !== true) {
    $fail('create success envelope broken');
}

// Product 404 → code + error_code
$notFound = Response::error('not found', HttpStatus::NOT_FOUND);
if ($notFound->getStatusCode() !== HttpStatus::NOT_FOUND) {
    $fail('product 404 status');
}
$body = $notFound->getData();
if (($body['code'] ?? null) !== HttpStatus::NOT_FOUND) {
    $fail('product 404 body code');
}
if (($body['error_code'] ?? null) !== ApiErrorCode::NOT_FOUND) {
    $fail('product 404 error_code must be not_found');
}

// Profile validation → top-level errors + 422 + validation_failed
$validation = Response::errorWithCode(
    ApiErrorCode::VALIDATION_FAILED,
    'validation failed',
    HttpStatus::UNPROCESSABLE_ENTITY,
    ['email' => ['Invalid email']],
    ['errors' => ['email' => ['Invalid email']]],
);
$vBody = $validation->getData();
if ($validation->getStatusCode() !== HttpStatus::UNPROCESSABLE_ENTITY) {
    $fail('profile validation HTTP must be 422');
}
if (($vBody['error_code'] ?? null) !== ApiErrorCode::VALIDATION_FAILED) {
    $fail('profile validation error_code');
}
if (($vBody['errors']['email'][0] ?? null) !== 'Invalid email') {
    $fail('profile validation top-level errors');
}
if (($vBody['data']['errors']['email'][0] ?? null) !== 'Invalid email') {
    $fail('profile validation BC data.errors mirror');
}

// Cart/order domain: business_rule + payload in data (not errors)
$cartFail = Response::errorWithCode(
    ApiErrorCode::BUSINESS_RULE,
    'cart rule',
    HttpStatus::BAD_REQUEST,
    null,
    ['status' => 'empty'],
);
$cBody = $cartFail->getData();
if (($cBody['error_code'] ?? null) !== ApiErrorCode::BUSINESS_RULE) {
    $fail('cart domain error_code');
}
if (!array_key_exists('errors', $cBody) || $cBody['errors'] !== null) {
    $fail('cart domain must keep errors null');
}
if (($cBody['data']['status'] ?? null) !== 'empty') {
    $fail('cart domain payload must live in data');
}

// Domain MS2 transform: lift data.errors → top-level errors + 422
$orderValidation = DomainMs2Response::failure(
    'validation failed',
    ['order' => ['id' => 1], 'errors' => ['email' => 'Required']],
);
$ov = $orderValidation->getData();
if ($orderValidation->getStatusCode() !== HttpStatus::UNPROCESSABLE_ENTITY) {
    $fail('domain field map must be HTTP 422');
}
if (($ov['error_code'] ?? null) !== ApiErrorCode::VALIDATION_FAILED) {
    $fail('domain field map error_code');
}
if (($ov['errors']['email'] ?? null) !== 'Required') {
    $fail('domain field map must lift to top-level errors');
}
if (isset($ov['data']['errors'])) {
    $fail('lifted field map must not remain in data.errors');
}
if (($ov['data']['order']['id'] ?? null) !== 1) {
    $fail('domain context must remain in data');
}

$orderNotFound = DomainMs2Response::failure('ms3_err_order_nf', null);
if ($orderNotFound->getStatusCode() !== HttpStatus::NOT_FOUND) {
    $fail('domain _nf message must map to HTTP 404');
}
if (($orderNotFound->getData()['error_code'] ?? null) !== ApiErrorCode::NOT_FOUND) {
    $fail('domain not found error_code');
}

// Conflict: existing_id in data
$conflict = Response::errorWithCode(
    ApiErrorCode::CONFLICT,
    'exists',
    HttpStatus::CONFLICT,
    null,
    ['existing_id' => 42],
);
if ($conflict->getStatusCode() !== HttpStatus::CONFLICT) {
    $fail('address conflict status');
}
if (($conflict->getData()['data']['existing_id'] ?? null) !== 42) {
    $fail('address conflict existing_id in data');
}
if (!array_key_exists('errors', $conflict->getData()) || $conflict->getData()['errors'] !== null) {
    $fail('conflict must not stuff payload into errors');
}

// Token / rate-limit machine codes
$tokenExpired = Response::errorWithCode(
    ApiErrorCode::TOKEN_EXPIRED,
    'ms3_err_token_expired',
    HttpStatus::UNAUTHORIZED
);
if (($tokenExpired->getData()['error_code'] ?? null) !== ApiErrorCode::TOKEN_EXPIRED) {
    $fail('token_expired error_code');
}

$rateLimited = Response::errorWithCode(
    ApiErrorCode::RATE_LIMITED,
    'ms3_err_rate_limit',
    HttpStatus::TOO_MANY_REQUESTS
);
if ($rateLimited->getStatusCode() !== HttpStatus::TOO_MANY_REQUESTS) {
    $fail('rate limit status');
}
if (($rateLimited->getData()['error_code'] ?? null) !== ApiErrorCode::RATE_LIMITED) {
    $fail('rate_limited error_code');
}

// ApiErrorCode::fromHttpStatus mapping
$maps = [
    HttpStatus::UNAUTHORIZED => ApiErrorCode::UNAUTHORIZED,
    HttpStatus::FORBIDDEN => ApiErrorCode::FORBIDDEN,
    HttpStatus::NOT_FOUND => ApiErrorCode::NOT_FOUND,
    HttpStatus::CONFLICT => ApiErrorCode::CONFLICT,
    HttpStatus::TOO_MANY_REQUESTS => ApiErrorCode::RATE_LIMITED,
    HttpStatus::UNPROCESSABLE_ENTITY => ApiErrorCode::VALIDATION_FAILED,
    HttpStatus::BAD_REQUEST => ApiErrorCode::BAD_REQUEST,
    HttpStatus::INTERNAL_SERVER_ERROR => ApiErrorCode::INTERNAL_ERROR,
];
foreach ($maps as $status => $expected) {
    if (ApiErrorCode::fromHttpStatus($status) !== $expected) {
        $fail("fromHttpStatus({$status}) expected {$expected}");
    }
}

fwrite(STDOUT, "OK WebApiErrorContractTest\n");
exit(0);
