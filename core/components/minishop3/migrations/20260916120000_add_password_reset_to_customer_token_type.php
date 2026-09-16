<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Allow password_reset on ms3_customer_tokens.type (ForgotPassword / ResetPassword).
 */
final class AddPasswordResetToCustomerTokenType extends AbstractMigration
{
    private const ENUM_WITH_RESET = "'api','refresh','magic_link','email_verification','password_reset'";

    private const ENUM_WITHOUT_RESET = "'api','refresh','magic_link','email_verification'";

    public function up(): void
    {
        $table = $this->resolveTokenTable();
        if ($table === null) {
            return;
        }

        $type = $this->columnTypeSql($table);
        if ($type === '' || str_contains($type, 'password_reset')) {
            return;
        }

        $this->modifyTypeEnum($table, self::ENUM_WITH_RESET);
    }

    public function down(): void
    {
        $table = $this->resolveTokenTable();
        if ($table === null) {
            return;
        }

        $type = $this->columnTypeSql($table);
        if ($type === '' || !str_contains($type, 'password_reset')) {
            return;
        }

        $countRow = $this->fetchRow(
            "SELECT COUNT(*) AS c FROM `{$table}` WHERE `type` = 'password_reset'"
        );
        $count = is_array($countRow) ? (int) ($countRow['c'] ?? 0) : 0;
        if ($count > 0) {
            return;
        }

        $this->modifyTypeEnum($table, self::ENUM_WITHOUT_RESET);
    }

    private function resolveTokenTable(): ?string
    {
        if (!$this->hasTable('ms3_customer_tokens')) {
            return null;
        }

        return (string) ($this->getAdapter()->getOption('table_prefix') ?? '') . 'ms3_customer_tokens';
    }

    private function modifyTypeEnum(string $table, string $enumValues): void
    {
        $this->execute(
            "ALTER TABLE `{$table}` "
            . 'MODIFY COLUMN `type` ENUM(' . $enumValues . ") NOT NULL DEFAULT 'api'"
        );
    }

    private function columnTypeSql(string $table): string
    {
        $row = $this->fetchRow("SHOW COLUMNS FROM `{$table}` LIKE 'type'");

        return is_array($row) ? (string) ($row['Type'] ?? '') : '';
    }
}
