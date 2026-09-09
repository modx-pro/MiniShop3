<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx;

use MiniShop3\MiniShop3;
use MiniShop3\Tests\Modx\Support\ExtraTestCase;

final class TokenNameSettingTest extends ExtraTestCase
{
    public function testSetSettingWritesDatabaseAndMemory(): void
    {
        $this->setSetting('ms3_token_name', 'ms3_test_token');

        $this->assertSettingEquals('ms3_token_name', 'ms3_test_token');
        self::assertSame('ms3_test_token', $this->modx->getOption('ms3_token_name'));
    }

    public function testCatalogSettingsRoundTrip(): void
    {
        $settings = [
            'ms3_currency' => 'EUR',
            'ms3_register_frontend' => '0',
            'ms3_customer_require_email_verification' => '0',
            'ms3_customer_send_welcome_email' => '0',
            'ms3_password_min_length' => '10',
            'ms3_product_show_in_tree_default' => '1',
        ];

        foreach ($settings as $key => $value) {
            $this->setSetting($key, $value);
            $this->assertSettingEquals($key, $value);
            self::assertSame($value, (string) $this->modx->getOption($key));
        }
    }

    public function testMs3ServiceAndRegistryAreRegistered(): void
    {
        self::assertTrue($this->modx->services->has('ms3'));
        self::assertInstanceOf(MiniShop3::class, $this->modx->services->get('ms3'));
        self::assertTrue($this->modx->services->has('ms3_order_status'));
    }

    public function testPhinxConfigUsesLiveModxDatabase(): void
    {
        $modx = $this->modx;
        $config = require $this->extraCorePath() . 'phinx.php';

        self::assertSame(
            $modx->getOption('dbname'),
            $config['environments']['production']['name'] ?? null,
        );
    }
}
