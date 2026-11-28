<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Remove the dead ms3_customer_api_token_ttl setting (#797).
 *
 * Nothing in src/ read the key since the httpOnly cookie transition, yet it stayed
 * in the transport package next to the working ms3_customer_token_ttl with the same
 * value and area, so admins edited the wrong one. The setting row is deleted here so
 * installed sites lose the dangling control too.
 */
final class RemoveCustomerApiTokenTtlSetting extends AbstractMigration
{
    private const SETTING_KEY = 'ms3_customer_api_token_ttl';

    private const NAMESPACE = 'minishop3';

    public function up(): void
    {
        $prefix = $this->getAdapter()->getOption('table_prefix');
        $table = $prefix . 'system_settings';
        $pdo = $this->getAdapter()->getConnection();

        $stmt = $pdo->prepare(
            "DELETE FROM `{$table}` WHERE `key` = :key AND `namespace` = :namespace"
        );
        $stmt->execute([
            'key' => self::SETTING_KEY,
            'namespace' => self::NAMESPACE,
        ]);
    }

    public function down(): void
    {
        // Intentionally empty: the removed setting was never read, so nothing to restore.
    }
}
