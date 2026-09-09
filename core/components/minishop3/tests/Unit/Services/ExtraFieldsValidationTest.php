<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Services\ExtraFieldsService;
use MiniShop3\Services\Grid\GridColumnRules;
use MiniShop3\Services\Grid\Mysql8ReservedKeywords;
use MiniShop3\Services\MigrationGenerator;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Field-key and host-class rules for Extra Fields (#645, #655).
 */
final class ExtraFieldsValidationTest extends TestCase
{
    private bool $savedCalled = false;

    /** @var array{key: string, params: array<string, mixed>}|null */
    private ?array $lastLexiconCall = null;

    protected function setUp(): void
    {
        $this->savedCalled = false;
        $this->lastLexiconCall = null;
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 2) . '/stubs/ModxStub.php';
        }
        // xpdo/modResource parents come from PHPUnit bootstrap (tests/support/*).
    }

    public function testGridColumnRulesRejectsCyrillicAndAcceptsAsciiKey(): void
    {
        $this->assertFalse(GridColumnRules::isValidSqlIdentifier('гарантия'));
        $this->assertFalse(GridColumnRules::isValidSqlIdentifier('warranty months'));
        $this->assertTrue(GridColumnRules::isValidSqlIdentifier('warranty_months'));
        $this->assertSame(
            GridColumnRules::CLASSIFY_INVALID,
            GridColumnRules::classifySqlIdentifier('гарантия')
        );
    }

    public function testGridColumnRulesRejectsMysqlReservedWords(): void
    {
        foreach (['order', 'rank', 'groups', 'function', 'system', 'ORDER', 'Rank'] as $name) {
            $this->assertFalse(
                GridColumnRules::isValidSqlIdentifier($name),
                "Expected reserved identifier to be rejected: {$name}"
            );
            $this->assertTrue(GridColumnRules::isMysqlReservedIdentifier($name));
            $this->assertSame(
                GridColumnRules::CLASSIFY_RESERVED,
                GridColumnRules::classifySqlIdentifier($name)
            );
            $this->assertTrue(GridColumnRules::matchesSqlIdentifierPattern($name));
        }

        foreach (['sort_order', 'preview_url', 'category_name', 'warranty_months', 'status', 'name', 'type'] as $name) {
            $this->assertTrue(
                GridColumnRules::isValidSqlIdentifier($name),
                "Expected valid identifier to pass: {$name}"
            );
            $this->assertSame(GridColumnRules::CLASSIFY_OK, GridColumnRules::classifySqlIdentifier($name));
        }

        $this->assertSame('sort_order', GridColumnRules::suggestSqlIdentifierAlternative('order'));
        $this->assertSame('sort_index', GridColumnRules::suggestSqlIdentifierAlternative('rank'));
        $this->assertSame('user_groups', GridColumnRules::suggestSqlIdentifierAlternative('groups'));

        $list = Mysql8ReservedKeywords::LIST;
        $this->assertContains('order', $list);
        $this->assertContains('rank', $list);
        $this->assertContains('groups', $list);
        $this->assertContains('array', $list);
        $this->assertSame(count($list), count(array_unique($list)));
        foreach ($list as $word) {
            $this->assertSame(strtolower($word), $word);
            $this->assertNotSame('', $word);
        }
        $this->assertNotContains('status', $list);
        $this->assertNotContains('name', $list);
        $this->assertNotContains('type', $list);
    }

    public function testMigrationGeneratorRejectsResourceStiClasses(): void
    {
        $generator = new MigrationGenerator($this->createModxStub());

        $this->assertTrue($generator->canHostExtraField(msProductData::class));
        $this->assertFalse($generator->canHostExtraField(msProduct::class));
        $this->assertFalse($generator->canHostExtraField(msCategory::class));
        $this->assertSame('ms3_products', $generator->resolveTableName(msProductData::class));
    }

    public function testCreateFieldRejectsUnsupportedClassBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msProduct::class,
            'key' => 'warranty_months',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('class', $result['field'] ?? null);
        $this->assertSame('ms3_err_extra_field_class_unsupported', $result['message'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    public function testCreateFieldRejectsCyrillicKeyBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msProductData::class,
            'key' => 'гарантия',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('key', $result['field'] ?? null);
        $this->assertSame('ms3_err_extra_field_key_invalid', $result['message'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    public function testCreateFieldRejectsMysqlReservedKeyBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msProductData::class,
            'key' => 'order',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('key', $result['field'] ?? null);
        $this->assertSame('ms3_err_extra_field_key_reserved', $result['message'] ?? null);
        $this->assertSame('ms3_err_extra_field_key_reserved', $this->lastLexiconCall['key'] ?? null);
        $this->assertSame('order', $this->lastLexiconCall['params']['key'] ?? null);
        $this->assertSame('sort_order', $this->lastLexiconCall['params']['suggestion'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    public function testUpdateFieldGrandfathersReservedKeyWithoutRevalidation(): void
    {
        $field = new class {
            /** @var array<string, mixed> */
            private array $data = [
                'id' => 1,
                'class' => 'MiniShop3\\Model\\msOrderAddress',
                'key' => 'order',
                'label' => 'Old',
                'xtype' => 'textfield',
                'active' => true,
                'description' => null,
                'select_options' => null,
                'repeater_config' => null,
                'key_value_config' => null,
            ];

            public function get($key)
            {
                return $this->data[$key] ?? null;
            }

            public function set($key, $value): void
            {
                $this->data[$key] = $value;
            }

            public function toArray(): array
            {
                return $this->data;
            }

            public function save(): bool
            {
                return true;
            }
        };

        $service = new ExtraFieldsService($this->createModxStub(existingField: $field));
        $result = $service->updateField(1, ['label' => 'Renamed']);

        $this->assertTrue($result['success']);
        $this->assertSame('order', $field->get('key'));
        $this->assertSame('Renamed', $field->get('label'));
    }

    public function testCreateFieldRejectsCategoryStiClassBeforeSave(): void
    {
        $service = new ExtraFieldsService($this->createModxStub());
        $result = $service->createField([
            'class' => msCategory::class,
            'key' => 'warranty_months',
            'dbtype' => 'varchar',
            'phptype' => 'string',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('class', $result['field'] ?? null);
        $this->assertFalse($this->savedCalled);
    }

    private function createModxStub(object $existingField = null): modX
    {
        $test = $this;

        return new class ($test, $existingField) extends modX {
            public $cacheManager;

            public function __construct(
                private ExtraFieldsValidationTest $test,
                private ?object $existingField
            ) {
                parent::__construct();
                $this->cacheManager = new class {
                    public function delete($key, $options = []): bool
                    {
                        return true;
                    }
                };
            }

            public function getOption($key, $options = null, $default = null)
            {
                if ($key === 'dbtype') {
                    return 'mysql';
                }

                return $default;
            }

            public function log($level, $msg, $target = '', $def = '', $file = '', $line = ''): void
            {
            }

            public function lexicon($key, $params = [], $language = ''): string
            {
                $this->test->rememberLexiconCall((string) $key, is_array($params) ? $params : []);

                return (string) $key;
            }

            public function newObject($className, $fields = [])
            {
                $this->test->markSavedCalled();

                return new class {
                    public function fromArray($data): void
                    {
                    }

                    public function save(): bool
                    {
                        return true;
                    }

                    public function remove(): bool
                    {
                        return true;
                    }

                    public function get($key)
                    {
                        return null;
                    }

                    public function toArray(): array
                    {
                        return [];
                    }
                };
            }

            public function getObject($className, $criteria = null)
            {
                if ($this->existingField !== null && $className === msExtraField::class) {
                    return $this->existingField;
                }

                return null;
            }
        };
    }

    public function markSavedCalled(): void
    {
        $this->savedCalled = true;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function rememberLexiconCall(string $key, array $params): void
    {
        $this->lastLexiconCall = ['key' => $key, 'params' => $params];
    }
}
