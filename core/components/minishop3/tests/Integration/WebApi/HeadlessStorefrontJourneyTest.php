<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyOrder;
use MiniShop3\Tests\Integration\WebApi\Support\JourneyProductCatalog;

/**
 * Phase A: headless Nuxt happy-path through Router + web.php + envelope (#574).
 */
final class HeadlessStorefrontJourneyTest extends WebApiTestCase
{
    public function testHappyPathCatalogCartAuthCheckoutAndOrderGet(): void
    {
        $list = $this->dispatch('GET', '/api/v1/product/list', ['limit' => 10]);
        self::assertSame(HttpStatus::OK, $list['status']);
        self::assertTrue($list['success']);
        self::assertSame(1, $list['data']['total'] ?? null);
        self::assertSame(
            JourneyProductCatalog::FIXTURE_PRODUCT_ID,
            $list['data']['results'][0]['id'] ?? null
        );

        $get = $this->dispatch(
            'GET',
            '/api/v1/product/get/' . JourneyProductCatalog::FIXTURE_PRODUCT_ID
        );
        self::assertSame(HttpStatus::OK, $get['status']);
        self::assertTrue($get['success']);
        self::assertSame(JourneyProductCatalog::FIXTURE_PRODUCT_ID, $get['data']['id'] ?? null);

        $add = $this->dispatch('POST', '/api/v1/cart/add', [], [
            'id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID,
            'count' => 2,
            'options' => ['color' => 'red'],
        ]);
        self::assertSame(HttpStatus::OK, $add['status'], $add['message']);
        self::assertTrue($add['success']);
        $productKey = (string) ($add['data']['last_key'] ?? '');
        self::assertNotSame('', $productKey);
        self::assertSame(2, $add['data']['status']['total_count'] ?? null);
        $guestToken = $this->lastMs3Token();
        self::assertNotSame('', $guestToken);

        $change = $this->dispatch(
            'POST',
            '/api/v1/cart/change',
            [],
            ['product_key' => $productKey, 'count' => 3],
            [],
            $guestToken
        );
        self::assertSame(HttpStatus::OK, $change['status']);
        self::assertTrue($change['success']);
        self::assertSame(3, $change['data']['status']['total_count'] ?? null);

        $changeOpt = $this->dispatch(
            'POST',
            '/api/v1/cart/change-option',
            [],
            ['product_key' => $productKey, 'options' => ['color' => 'blue']],
            [],
            $guestToken
        );
        self::assertSame(HttpStatus::OK, $changeOpt['status']);
        self::assertTrue($changeOpt['success']);
        $productKey = (string) ($changeOpt['data']['last_key'] ?? $productKey);
        self::assertSame('blue', $changeOpt['data']['cart'][$productKey]['options']['color'] ?? null);

        $cart = $this->dispatch('GET', '/api/v1/cart/get', [], [], [], $guestToken);
        self::assertSame(HttpStatus::OK, $cart['status']);
        self::assertTrue($cart['success']);
        self::assertSame(3, $cart['data']['status']['total_count'] ?? null);
        self::assertSame(300.0, $cart['data']['status']['total_cost'] ?? null);

        $login = $this->dispatch('POST', '/api/v1/customer/login', [], [], [], $guestToken);
        self::assertSame(HttpStatus::OK, $login['status']);
        self::assertTrue($login['success']);
        $userToken = (string) ($login['data']['token'] ?? '');
        self::assertNotSame('', $userToken);
        self::assertNotSame($guestToken, $userToken);

        $cartAfterLogin = $this->dispatch('GET', '/api/v1/cart/get', [], [], [], $userToken);
        self::assertSame(HttpStatus::OK, $cartAfterLogin['status']);
        self::assertTrue($cartAfterLogin['success']);
        self::assertSame(3, $cartAfterLogin['data']['status']['total_count'] ?? null);

        $addr = $this->dispatch(
            'POST',
            '/api/v1/order/address/set',
            [],
            ['address_hash' => 'addr-fixture-1'],
            [],
            $userToken
        );
        self::assertSame(HttpStatus::OK, $addr['status'], $addr['message']);
        self::assertTrue($addr['success']);

        $delivery = $this->dispatch(
            'POST',
            '/api/v1/order/set',
            [],
            ['fields' => ['delivery_id' => JourneyOrder::FIXTURE_DELIVERY_ID]],
            [],
            $userToken
        );
        self::assertSame(HttpStatus::OK, $delivery['status']);
        self::assertTrue($delivery['success']);

        $payment = $this->dispatch(
            'POST',
            '/api/v1/order/set',
            [],
            ['fields' => ['payment_id' => JourneyOrder::FIXTURE_PAYMENT_ID]],
            [],
            $userToken
        );
        self::assertSame(HttpStatus::OK, $payment['status']);
        self::assertTrue($payment['success']);

        $cost = $this->dispatch('GET', '/api/v1/order/cost', [], [], [], $userToken);
        self::assertSame(HttpStatus::OK, $cost['status']);
        self::assertTrue($cost['success']);
        self::assertSame(300.0, $cost['data']['cart_cost'] ?? null);
        self::assertSame(50.0, $cost['data']['delivery_cost'] ?? null);
        self::assertSame(0.0, $cost['data']['payment_cost'] ?? null);
        self::assertSame(350.0, $cost['data']['cost'] ?? null);

        $submit = $this->dispatch('POST', '/api/v1/order/submit', [], ['data' => []], [], $userToken);
        self::assertSame(HttpStatus::OK, $submit['status'], $submit['message']);
        self::assertTrue($submit['success']);
        self::assertSame('/checkout/success?order=501', $submit['data']['redirect'] ?? null);

        $orderId = (int) ($submit['data']['msorder'] ?? 0);
        $this->modx->customerOrders->seedOrder($orderId, 42);

        $orders = $this->dispatch('GET', '/api/v1/customer/orders', [], [], [], $userToken);
        self::assertSame(HttpStatus::OK, $orders['status']);
        self::assertTrue($orders['success']);
        self::assertSame(1, $orders['data']['total'] ?? null);

        $one = $this->dispatch('GET', '/api/v1/customer/orders/' . $orderId, [], [], [], $userToken);
        self::assertSame(HttpStatus::OK, $one['status']);
        self::assertTrue($one['success']);
        self::assertSame($orderId, $one['data']['id'] ?? null);
    }

    public function testCookieAuthPathReachesCartGet(): void
    {
        $this->modx->journeyTokens->putToken('cookie-guest-token', 0);

        $add = $this->dispatch(
            'POST',
            '/api/v1/cart/add',
            [],
            ['id' => JourneyProductCatalog::FIXTURE_PRODUCT_ID, 'count' => 1],
            [],
            null,
            'cookie-guest-token'
        );
        self::assertSame(HttpStatus::OK, $add['status'], $add['message']);
        self::assertTrue($add['success']);

        $cart = $this->dispatch(
            'GET',
            '/api/v1/cart/get',
            [],
            [],
            [],
            null,
            'cookie-guest-token'
        );
        self::assertSame(HttpStatus::OK, $cart['status']);
        self::assertTrue($cart['success']);
        self::assertGreaterThanOrEqual(1, $cart['data']['status']['total_count'] ?? 0);
    }
}
