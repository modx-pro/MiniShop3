<?php

namespace MiniShop3\Utils;

use MODX\Revolution\modX;
use PDO;
use PDOException;

class DBManager
{
    private modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function hasColumn($class, $column): bool
    {
        if (!empty($class)) {
            try {
                $tableName = $this->modx->getTableName($class);
                if ($tableName) {
                    $stmt = $this->modx->prepare("SHOW COLUMNS FROM {$tableName} LIKE ?");
                    $stmt->execute([$column]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        return true;
                    }
                }
            } catch (PDOException $e) {
                return false;
            }
        }
        return false;
    }
}
