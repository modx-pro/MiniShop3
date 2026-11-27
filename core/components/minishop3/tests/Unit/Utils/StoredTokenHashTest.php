<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Utils;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Utils\StoredTokenHash;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

final class StoredTokenHashTest extends TestCase
{
    public function testHashIsPrefixedSoMigrationCannotDoubleHashIt(): void
    {
        self::assertStringStartsWith('sha256$', StoredTokenHash::hash('plain-token'));
        self::assertSame(hash('sha256', 'plain-token'), substr(StoredTokenHash::hash('plain-token'), 7));
        self::assertDoesNotMatchRegularExpression('/^[0-9a-f]{64}$/', StoredTokenHash::hash('plain-token'));
    }

    public function testSetStoresHashNotPlaintext(): void
    {
        $object = $this->fakeObject([]);
        StoredTokenHash::set($object, 'plain-token');

        self::assertSame(StoredTokenHash::hash('plain-token'), $object->get('token'));
        self::assertNotSame('plain-token', $object->get('token'));
    }

    public function testFindChecksHashFirstAndKeepsCriteria(): void
    {
        $hashed = $this->fakeObject(['token' => StoredTokenHash::hash('plain-token')]);
        $modx = $this->modx([StoredTokenHash::hash('plain-token') => $hashed]);

        self::assertSame(
            $hashed,
            StoredTokenHash::find($modx, 'SomeClass', 'plain-token', [
                'status_id' => 1,
                'context' => 'web',
            ])
        );
        self::assertCount(1, $modx->lookupKeys);
        self::assertSame([
            'status_id' => 1,
            'context' => 'web',
            'token' => StoredTokenHash::hash('plain-token'),
        ], $modx->lookupKeys[0]);
    }

    public function testLegacyPlaintextRowIsFoundWithoutRewritingIt(): void
    {
        $legacy = $this->fakeObject(['token' => 'plain-token']);
        $modx = $this->modx(['plain-token' => $legacy]);

        self::assertSame($legacy, StoredTokenHash::find($modx, 'SomeClass', 'plain-token'));
        self::assertSame(
            [
                ['token' => StoredTokenHash::hash('plain-token')],
                ['token' => 'plain-token'],
            ],
            $modx->lookupKeys,
            'hash lookup must be attempted first'
        );
        self::assertSame('plain-token', $legacy->get('token'));
        self::assertFalse($legacy->saved);
    }

    public function testCustomFieldIsSupported(): void
    {
        $object = $this->fakeObject([]);
        StoredTokenHash::set($object, 'plain-token', 'access_token');
        $modx = $this->modx([StoredTokenHash::hash('plain-token') => $object], 'access_token');

        self::assertSame(
            $object,
            StoredTokenHash::find($modx, 'SomeClass', 'plain-token', [], 'access_token')
        );
    }

    public function testEmptyTokenIsNeverLookedUp(): void
    {
        $modx = $this->modx([]);

        self::assertNull(StoredTokenHash::find($modx, 'SomeClass', ''));
        self::assertSame([], $modx->lookupKeys);
    }

    private function fakeObject(array $fields): object
    {
        return new class ($fields) {
            public array $fields;
            public bool $saved = false;

            public function __construct(array $fields)
            {
                $this->fields = $fields;
            }

            public function get($key)
            {
                return $this->fields[$key] ?? null;
            }

            public function set($key, $value)
            {
                $this->fields[$key] = $value;

                return true;
            }

            public function save()
            {
                $this->saved = true;

                return true;
            }
        };
    }

    private function modx(array $objects, string $field = 'token'): modX
    {
        return new class ($objects, $field) extends modX {
            public array $lookupKeys = [];

            public function __construct(private array $objects, private string $field)
            {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                $this->lookupKeys[] = $criteria;
                $key = is_array($criteria) ? ($criteria[$this->field] ?? null) : null;

                return $this->objects[$key] ?? null;
            }
        };
    }
}
