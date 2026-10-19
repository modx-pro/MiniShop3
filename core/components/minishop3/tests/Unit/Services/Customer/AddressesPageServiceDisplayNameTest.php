<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Customer;

use MiniShop3\Services\Customer\AddressesPageService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Fallback address name must be localized (#819).
 */
final class AddressesPageServiceDisplayNameTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testUsesLexiconLabelsForBuildingAndRoom(): void
    {
        $service = $this->service([
            'ms3_customer_building' => 'Дом',
            'ms3_customer_room' => 'Кв.',
        ]);

        self::assertSame(
            'Москва, Тверская, Дом 12, Кв. 34',
            $service->displayName([
                'name' => null,
                'city' => 'Москва',
                'street' => 'Тверская',
                'building' => '12',
                'room' => '34',
            ])
        );
    }

    public function testPrefersSavedName(): void
    {
        $service = $this->service([
            'ms3_customer_building' => 'Building',
            'ms3_customer_room' => 'Apt.',
        ]);

        self::assertSame(
            'Home',
            $service->displayName([
                'name' => 'Home',
                'city' => 'Москва',
                'street' => 'Тверская',
                'building' => '12',
            ])
        );
    }

    public function testSkipsEmptyPartsAndKeepsZeroValues(): void
    {
        $service = $this->service([
            'ms3_customer_building' => 'Building',
            'ms3_customer_room' => 'Apt.',
        ]);

        self::assertSame(
            'Moscow, Tverskaya, Building 0',
            $service->displayName([
                'city' => 'Moscow',
                'street' => '  Tverskaya  ',
                'building' => '0',
                'room' => '',
            ])
        );
    }

    /**
     * @param array<string, string> $lexiconMap
     */
    private function service(array $lexiconMap): AddressesPageService
    {
        $modx = new class ($lexiconMap) extends modX {
            /** @param array<string, string> $lexiconMap */
            public function __construct(private array $lexiconMap)
            {
                parent::__construct();
            }

            /**
             * @param array<string, mixed> $params
             */
            public function lexicon(string $key, array $params = []): string
            {
                return $this->lexiconMap[$key] ?? parent::lexicon($key, $params);
            }
        };

        return new class ($modx) extends AddressesPageService {
            public function __construct(modX $modx)
            {
                $this->modx = $modx;
            }

            /** @param array<string, mixed> $addressData */
            public function displayName(array $addressData): string
            {
                return $this->getDisplayName($addressData);
            }
        };
    }
}