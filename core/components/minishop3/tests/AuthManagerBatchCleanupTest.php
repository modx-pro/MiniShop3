<?php

/**
 * #999b: expired-token cleanup must use the batched DELETE path, not hydrate
 * every row through getCollection(). A single-quoted \x60 in trim()'s charlist
 * used to leave xPDO's backticks in place, silently disabling the batch.
 *
 * Run: php tests/AuthManagerBatchCleanupTest.php
 */

declare(strict_types=1);

namespace MODX\Revolution {
    class modX
    {
        public const LOG_LEVEL_INFO = 3;
        public const LOG_LEVEL_ERROR = 1;

        /** @var \PDO|null */
        public $pdo;

        /** @var list<string> */
        public array $logs = [];

        public function __construct(\PDO $pdo)
        {
            $this->pdo = $pdo;
        }

        public function getTableName($className, $default = null)
        {
            // xPDO quotes and prefixes table names.
            return '`modx_ms3_customer_tokens`';
        }

        public function log($level, $message): void
        {
            $this->logs[] = (string) $message;
        }

        public function getOption($key, $options = null, $default = null)
        {
            return $default;
        }
    }
}

namespace {
    use MiniShop3\Services\Customer\AuthManager;
    use MODX\Revolution\modX;

    require_once dirname(__DIR__) . '/vendor/autoload.php';

    final class CleanupSpyStatement extends \PDOStatement
    {
        public function execute($params = null): bool
        {
            $GLOBALS['ms3_cleanup_executes'][] = $params;

            return parent::execute($params);
        }
    }

    $fail = static function (string $message): never {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    };

    $pdo = new \PDO('sqlite::memory:');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [CleanupSpyStatement::class]);
    $GLOBALS['ms3_cleanup_executes'] = [];
    $pdo->exec('CREATE TABLE modx_ms3_customer_tokens (id INTEGER PRIMARY KEY, expires_at TEXT)');
    for ($i = 0; $i < 7; $i++) {
        $pdo->exec("INSERT INTO modx_ms3_customer_tokens (expires_at) VALUES ('2000-01-01 00:00:00')");
    }

    $modx = new modX($pdo);
    $auth = new AuthManager($modx);

    // Batch size 3 must issue 3 DELETE statements, not hydrate 7 objects.
    $deleted = $auth->cleanupExpiredTokens(3);
    if ($deleted !== 7) {
        $fail("Expected 7 deletions, got {$deleted}");
    }

    $executions = $GLOBALS['ms3_cleanup_executes'];
    if (count($executions) === 0) {
        $fail('Batched DELETE never executed — the quoted table name was not normalized (#999b)');
    }
    if (count($executions) !== 3) {
        $fail('Expected 3 batched DELETE executions for 7 rows with batch size 3, got ' . count($executions));
    }

    fwrite(STDOUT, "OK AuthManagerBatchCleanupTest (#999b)\n");
    exit(0);
}
