<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Make ms3_orders.num unique to prevent duplicate order numbers (#380).
 *
 * Empty draft numbers become NULL (MySQL UNIQUE allows multiple NULLs).
 * Existing duplicate non-empty nums are disambiguated before the index is added.
 */
final class AddUniqueIndexOnOrderNum extends AbstractMigration
{
    public function up(): void
    {
        $quoted = $this->quoteIdentifier($this->ordersTable());

        $table = $this->table('ms3_orders');
        $table->changeColumn('num', 'string', [
            'limit' => 20,
            'null' => true,
            'default' => null,
        ])->update();

        $this->execute("UPDATE {$quoted} SET `num` = NULL WHERE `num` IS NULL OR `num` = ''");

        $this->disambiguateDuplicateNums($quoted);
        $this->assertNoDuplicateNums($quoted);

        if (!$table->hasIndexByName('num')) {
            $table->addIndex(['num'], [
                'unique' => true,
                'name' => 'num',
            ])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('ms3_orders');

        if ($table->hasIndexByName('num')) {
            $table->removeIndexByName('num')->update();
        }

        $quoted = $this->quoteIdentifier($this->ordersTable());
        $this->execute("UPDATE {$quoted} SET `num` = '' WHERE `num` IS NULL");
    }

    private function ordersTable(): string
    {
        $prefix = (string)($this->getAdapter()->getOption('table_prefix') ?? '');

        return $prefix . 'ms3_orders';
    }

    private function disambiguateDuplicateNums(string $quotedTable): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $dupStmt = $pdo->query(
            "SELECT `num` FROM {$quotedTable}
             WHERE `num` IS NOT NULL AND `num` != ''
             GROUP BY `num`
             HAVING COUNT(*) > 1"
        );

        if ($dupStmt === false) {
            throw new \RuntimeException('Failed to detect duplicate order numbers before unique index');
        }

        $select = $pdo->prepare(
            "SELECT `id` FROM {$quotedTable} WHERE `num` = ? ORDER BY `id` ASC"
        );
        $update = $pdo->prepare(
            "UPDATE {$quotedTable} SET `num` = ? WHERE `id` = ?"
        );
        $exists = $pdo->prepare(
            "SELECT 1 FROM {$quotedTable} WHERE `num` = ? LIMIT 1"
        );

        foreach ($dupStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $num = (string)$row['num'];
            $select->execute([$num]);
            $ids = array_column($select->fetchAll(\PDO::FETCH_ASSOC), 'id');

            foreach (array_slice($ids, 1) as $index => $id) {
                $newNum = $this->uniqueDisambiguatedNum($exists, $num, (int)$id, $index + 1);
                if (!$update->execute([$newNum, (int)$id])) {
                    throw new \RuntimeException(
                        "Failed to disambiguate order id={$id} num={$num}"
                    );
                }
            }
        }
    }

    /**
     * @param \PDOStatement $exists
     */
    private function uniqueDisambiguatedNum($exists, string $num, int $id, int $ordinal): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $suffix = '-d' . $id . ($attempt > 0 ? 'x' . $attempt : '');
            if ($ordinal > 1 && $attempt === 0) {
                $suffix = '-d' . $id;
            }
            $baseLen = max(1, 20 - strlen($suffix));
            $candidate = substr($num, 0, $baseLen) . $suffix;
            if (strlen($candidate) > 20) {
                $candidate = substr($candidate, 0, 20);
            }

            $exists->execute([$candidate]);
            if ($exists->fetchColumn() === false) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Unable to allocate unique num for order id={$id}");
    }

    private function assertNoDuplicateNums(string $quotedTable): void
    {
        $pdo = $this->getAdapter()->getConnection();
        $stmt = $pdo->query(
            "SELECT `num` FROM {$quotedTable}
             WHERE `num` IS NOT NULL AND `num` != ''
             GROUP BY `num`
             HAVING COUNT(*) > 1
             LIMIT 1"
        );

        if ($stmt === false) {
            throw new \RuntimeException('Failed to verify unique order numbers before index');
        }

        if ($stmt->fetchColumn() !== false) {
            throw new \RuntimeException('Duplicate order numbers remain; refusing to add UNIQUE index');
        }
    }

    private function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
