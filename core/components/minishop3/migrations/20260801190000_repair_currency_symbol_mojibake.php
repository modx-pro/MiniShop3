<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Repair ms3_currency_symbol when the ruble was stored as ASCII "?" (mojibake).
 *
 * Self-contained: do not import MiniShop3 application classes — during transport
 * install/upgrade Phinx may run before the new Format.php (with DEFAULT_CURRENCY_SYMBOL) is loaded.
 *
 * @see https://github.com/modx-pro/MiniShop3/issues/497
 */
final class RepairCurrencySymbolMojibake extends AbstractMigration
{
    private const SETTING_KEY = 'ms3_currency_symbol';

    private const NAMESPACE = 'minishop3';

    private const MOJIBAKE = '?';

    /** Ruble sign (U+20BD). Keep in sync with MiniShop3\Utils\Format::DEFAULT_CURRENCY_SYMBOL */
    private const RUBLE_SYMBOL = "\u{20BD}";

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
            'symbol' => self::RUBLE_SYMBOL,
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
