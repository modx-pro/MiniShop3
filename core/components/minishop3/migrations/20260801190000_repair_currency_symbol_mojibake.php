<?php

declare(strict_types=1);

use MiniShop3\Utils\Format;
use Phinx\Migration\AbstractMigration;

/**
 * Repair ms3_currency_symbol when the ruble was stored as ASCII "?" (mojibake).
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/497
 */
final class RepairCurrencySymbolMojibake extends AbstractMigration
{
    private const SETTING_KEY = 'ms3_currency_symbol';

    private const NAMESPACE = 'minishop3';

    private const MOJIBAKE = '?';

    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "UPDATE `{$table}` SET `value` = :symbol"
            . " WHERE `key` = :key AND `namespace` = :namespace AND `value` = :broken"
        );
        $stmt->execute([
            'symbol' => Format::DEFAULT_CURRENCY_SYMBOL,
            'key' => self::SETTING_KEY,
            'namespace' => self::NAMESPACE,
            'broken' => self::MOJIBAKE,
        ]);
    }

    public function down(): void
    {
        // Intentionally empty: repair is data correction; rolling back would re-corrupt the setting.
    }
}
