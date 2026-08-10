<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Optional MySQL connection for Level-2 CI / local integration tests.
 *
 * Env:
 * - MS3_TEST_MYSQL_DSN (e.g. mysql:host=127.0.0.1;port=3306;dbname=ms3_test;charset=utf8mb4)
 * - MS3_TEST_MYSQL_USER (default: root)
 * - MS3_TEST_MYSQL_PASSWORD (default: empty)
 */
final class MysqlTestConnection
{
    public static function isConfigured(): bool
    {
        $dsn = getenv('MS3_TEST_MYSQL_DSN');

        return is_string($dsn) && $dsn !== '';
    }

    public static function connect(): PDO
    {
        $dsn = (string) getenv('MS3_TEST_MYSQL_DSN');
        $user = getenv('MS3_TEST_MYSQL_USER');
        $pass = getenv('MS3_TEST_MYSQL_PASSWORD');
        $user = is_string($user) && $user !== '' ? $user : 'root';
        $pass = is_string($pass) ? $pass : '';

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }

    /**
     * Skip the current test when MySQL env is missing or unreachable.
     */
    public static function requireOrSkip(TestCase $test): PDO
    {
        if (!self::isConfigured()) {
            $test->markTestSkipped('MS3_TEST_MYSQL_DSN is not set');
        }

        try {
            return self::connect();
        } catch (PDOException $e) {
            $test->markTestSkipped('MySQL unavailable: ' . $e->getMessage());
        }
    }
}
