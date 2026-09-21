<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Reset ms3_payment_on_failed_status package default from 5 to 0 (#754).
 *
 * Only touches rows that still carry the transport default and were never edited in Manager.
 */
final class ResetPaymentOnFailedStatusDefault extends AbstractMigration
{
    private const SETTING_KEY = 'ms3_payment_on_failed_status';

    private const NAMESPACE = 'minishop3';

    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "UPDATE `{$table}` SET `value` = '0'"
            . " WHERE `key` = :key AND `namespace` = :namespace AND `value` = '5'"
            . ' AND `editedon` IS NULL'
        );
        $stmt->execute([
            'key' => self::SETTING_KEY,
            'namespace' => self::NAMESPACE,
        ]);
    }

    public function down(): void
    {
        // Intentionally empty: do not revert sites that rely on the new default.
    }
}
