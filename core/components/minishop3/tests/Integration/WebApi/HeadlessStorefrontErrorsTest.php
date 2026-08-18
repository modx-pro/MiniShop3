<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyBrokenTokenMint;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyOrder;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyProductCatalog;

/**
 * Error / auth edge cases for headless Web API journey (#574).
 */
final class HeadlessStorefrontErrorsTest extends WebApiTestCase
{
    public function testProductGetUnknownIdReturns404Envelope(): void
    {
        $res = $this->dispatch('GET', '/api/v1/product/get/999999');
        $this->assertApiError($res, HttpStatus::NOT_FOUND, 'ms3_err_product_nf');
    }

    public function testInvalidBearerReturns401(): void
    {
        $res = $this->dispatch(
            'GET',
            '/api/v1/cart/get',
            [],
            [],
            [],
            'definitely-invalid-token'
        );
        $this->assertApiError($res, HttpStatus::UNAUTHORIZED, 'ms3_err_token_invalid');
    }

    public function testExpiredBearerReturns401(): void
    {
        $this->modx->journeyTokens->putToken(
            'expired-token',
            0,
            date('Y-m-d H:i:s', time() - 60)
        );

        $res = $this->dispatch('GET', '/api/v1/cart/get', [], [], [], 'expired-token');
        $this->assertApiError($res, HttpStatus::UNAUTHORIZED, 'ms3_err_token_expired');
    }

    public function testChangeOptionEmptyOptionsReturns400(): void
    {
        $add = $this->dispatch('POST', '/api/v1/cart/add', [], [
            'id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID,
            'count' => 1,
            'options' => ['size' => 'M'],
        ]);
        $token = $this->lastMs3Token();
        $key = (string) ($add['data']['last_key'] ?? '');

        $res = $this->dispatch(
            'POST',
            '/api/v1/cart/change-option',
            [],
            ['product_key' => $key, 'options' => []],
            [],
            $token
        );
        $this->assertApiError($res, HttpStatus::BAD_REQUEST, 'ms3_cart_change_options_error');
    }

    public function testSubmitEmptyCartReturnsBusinessError(): void
    {
        $token = $this->modx->journeyTokens->generateCustomerToken()['token'];
        $this->modx->journeyTokens->putToken($token, 42);

        $res = $this->dispatch(
            'POST',
            '/api/v1/order/submit',
            [],
            ['data' => []],
            [],
            $token
        );
        $this->assertApiError($res, HttpStatus::BAD_REQUEST, 'ms3_err_cart_empty');
    }

    public function testPaymentNotLinkedToDeliveryReturnsBusinessError(): void
    {
        $this->dispatch('POST', '/api/v1/cart/add', [], [
            'id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID,
            'count' => 1,
        ]);
        $token = $this->lastMs3Token();

        $this->dispatch(
            'POST',
            '/api/v1/order/set',
            [],
            ['fields' => ['delivery_id' => JourneyOrder::FIXTURE_DELIVERY_ID]],
            [],
            $token
        );

        $res = $this->dispatch(
            'POST',
            '/api/v1/order/set',
            [],
            ['fields' => ['payment_id' => JourneyOrder::UNLINKED_PAYMENT_ID]],
            [],
            $token
        );
        $this->assertApiError($res, HttpStatus::BAD_REQUEST, 'ms3_err_payment_not_linked');
    }

    public function testCustomerOrdersAsGuestReturns401(): void
    {
        $token = $this->modx->journeyTokens->generateCustomerToken()['token'];

        $res = $this->dispatch('GET', '/api/v1/customer/orders', [], [], [], $token);
        $this->assertApiError($res, HttpStatus::UNAUTHORIZED, 'ms3_customer_order_err_unauthorized');
    }

    public function testCartWithoutTokenAndFailedAutoMintReturns500(): void
    {
        $this->modx->setTokenService(new JourneyBrokenTokenMint());
        $this->router = $this->buildRouter($this->modx);

        $res = $this->dispatch('GET', '/api/v1/cart/get');
        // #583: mint failure is an internal fault (cannot issue guest session), not auth rejection.
        $this->assertApiError($res, HttpStatus::INTERNAL_SERVER_ERROR, 'ms3_customer_err_token_create');
    }
}
