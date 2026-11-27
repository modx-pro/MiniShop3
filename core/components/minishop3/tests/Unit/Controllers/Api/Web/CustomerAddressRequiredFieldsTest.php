<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Controllers\Api\Web;

use MiniShop3\Controllers\Api\Web\CustomerAddressController;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class CustomerAddressRequiredFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 4) . '/stubs/ModxStub.php';
        }
    }

    public function testOnlyCityAndStreetAreRequired(): void
    {
        $controller = new TestableCustomerAddressController(new modX());

        self::assertSame([], $controller->missing([
            'name' => '',
            'city' => 'Москва',
            'street' => 'Ленина',
            'building' => '',
        ]), 'empty name and building are valid');

        self::assertSame([], $controller->missing([
            'city' => 'Москва',
            'street' => 'Ленина',
        ]), 'name may be omitted entirely');

        $missing = $controller->missing(['city' => '   ', 'street' => '']);
        self::assertArrayHasKey('city', $missing);
        self::assertArrayHasKey('street', $missing);
        self::assertArrayNotHasKey('name', $missing);
        self::assertArrayNotHasKey('building', $missing);

        $nonScalar = $controller->missing(['city' => ['Moscow'], 'street' => 'Ленина']);
        self::assertArrayHasKey('city', $nonScalar, 'arrays are not valid city values');
    }
}

/** Exposes the protected validation rule for a focused unit test. */
final class TestableCustomerAddressController extends CustomerAddressController
{
    /** @param array<string, mixed> $data */
    public function missing(array $data): array
    {
        return $this->missingRequiredFields($data);
    }
}
