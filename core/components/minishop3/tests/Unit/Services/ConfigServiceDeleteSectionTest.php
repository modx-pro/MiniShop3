<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services;

require_once __DIR__ . '/../../stubs/ModxStub.php';

use MiniShop3\Services\ConfigService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * Regression #827: deleting a section must detach its product fields instead
 * of leaving them with an orphaned (and FK-restricted) section id.
 */
final class ConfigServiceDeleteSectionTest extends TestCase
{
    public function testDeleteSectionDetachesItsFieldsBeforeRemoval(): void
    {
        $section = new FakeSectionForDelete(7);
        $first = new FakeFieldForDelete(7);
        $second = new FakeFieldForDelete(7);
        $modx = $this->modx($section, [$first, $second]);

        self::assertTrue((new ConfigService($modx))->deleteSection('product_data', 'pricing'));

        self::assertSame([['section' => 7]], $modx->iteratorCriteria);
        self::assertNull($first->section);
        self::assertNull($second->section);
        self::assertTrue($first->saved);
        self::assertTrue($second->saved);
        self::assertTrue($section->removed);
    }

    public function testFieldsOfOtherSectionsAreNotTouched(): void
    {
        $section = new FakeSectionForDelete(7);
        $own = new FakeFieldForDelete(7);
        $other = new FakeFieldForDelete(9);
        $modx = $this->modx($section, [$own, $other]);

        self::assertTrue((new ConfigService($modx))->deleteSection('product_data', 'pricing'));

        self::assertNull($own->section);
        self::assertSame(9, $other->section);
        self::assertFalse($other->saved);
    }

    public function testSectionIsKeptWhenDetachingFails(): void
    {
        $section = new FakeSectionForDelete(7);
        $field = new FakeFieldForDelete(7, false);
        $modx = $this->modx($section, [$field]);

        self::assertFalse((new ConfigService($modx))->deleteSection('product_data', 'pricing'));
        self::assertFalse($section->removed);
    }

    public function testTransactionIsCommittedWhenSectionIsDeleted(): void
    {
        $section = new FakeSectionForDelete(7);
        $field = new FakeFieldForDelete(7);
        $modx = $this->transactionModx($section, [$field]);

        self::assertTrue((new ConfigService($modx))->deleteSection('product_data', 'pricing'));
        self::assertSame(1, $modx->begins);
        self::assertSame(1, $modx->commits);
        self::assertSame(0, $modx->rollbacks);
        self::assertNull($field->section);
    }

    public function testTransactionRollsBackWhenSectionRemovalFails(): void
    {
        $section = new FakeSectionForDelete(7, false);
        $field = new FakeFieldForDelete(7);
        $modx = $this->transactionModx($section, [$field]);

        self::assertFalse((new ConfigService($modx))->deleteSection('product_data', 'pricing'));
        self::assertSame(1, $modx->begins);
        self::assertSame(0, $modx->commits);
        self::assertSame(1, $modx->rollbacks);
    }

    public function testTransactionRollsBackWhenDetachFails(): void
    {
        $section = new FakeSectionForDelete(7);
        $field = new FakeFieldForDelete(7, false);
        $modx = $this->transactionModx($section, [$field]);

        self::assertFalse((new ConfigService($modx))->deleteSection('product_data', 'pricing'));
        self::assertSame(1, $modx->begins);
        self::assertSame(0, $modx->commits);
        self::assertSame(1, $modx->rollbacks);
        self::assertFalse($section->removed);
    }

    public function testGetPageFieldsProvidesLabelledDefaultSection(): void
    {
        $modx = new class extends modX {
            public function newQuery($className)
            {
                return new class {
                    public function sortby($field, $direction = 'ASC')
                    {
                    }
                };
            }

            public function getCollection($className, $criteria = null)
            {
                return [];
            }

            public function getIterator($className, $criteria = null)
            {
                return new \ArrayIterator([]);
            }
        };

        $service = new class ($modx) extends ConfigService {
            protected function getLexiconValue(
                string $key,
                string $namespace = 'minishop3',
                string $topic = 'default'
            ): string {
                return $key === 'ms3_vue_no_section' ? 'Без секции' : $key;
            }
        };

        $result = $service->getPageFields('product_data');

        self::assertArrayHasKey('default', $result['sections']);
        self::assertSame('default', $result['sections']['default']['id']);
        self::assertSame('Без секции', $result['sections']['default']['label']);
        self::assertNull($result['sections']['default']['sort_order']);
    }

    public function testMissingSectionIsNoop(): void
    {
        $modx = new class extends modX {
            public array $iteratorCriteria = [];

            public function getObject($className, $criteria = null)
            {
                return null;
            }

            public function getIterator($className, $criteria = null)
            {
                $this->iteratorCriteria[] = $criteria;

                return new \ArrayIterator([]);
            }
        };

        self::assertTrue((new ConfigService($modx))->deleteSection('product_data', 'missing'));
        self::assertSame([], $modx->iteratorCriteria);
    }

    private function transactionModx(FakeSectionForDelete $section, array $fields): modX
    {
        return new class ($section, $fields) extends modX {
            public ?\PDO $pdo = null;
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function __construct(
                private FakeSectionForDelete $section,
                private array $fields,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null)
            {
                return $this->section;
            }

            public function getIterator($className, $criteria = null)
            {
                return new \ArrayIterator($this->fields);
            }

            public function beginTransaction()
            {
                $this->begins++;
            }

            public function commit()
            {
                $this->commits++;
            }

            public function rollBack()
            {
                $this->rollbacks++;
            }
        };
    }

    private function modx(FakeSectionForDelete $section, array $fields): modX
    {
        return new class ($section, $fields) extends modX {
            public array $iteratorCriteria = [];

            public function __construct(
                private FakeSectionForDelete $section,
                private array $fields,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null)
            {
                return $this->section;
            }

            public function getIterator($className, $criteria = null)
            {
                $this->iteratorCriteria[] = $criteria;
                $sectionId = (int)($criteria['section'] ?? -1);
                $matched = array_values(array_filter(
                    $this->fields,
                    static fn (FakeFieldForDelete $field): bool => (int)$field->section === $sectionId
                ));

                return new \ArrayIterator($matched);
            }
        };
    }
}

final class FakeSectionForDelete
{
    public bool $removed = false;

    public function __construct(private int $id, private bool $removeResult = true)
    {
    }

    public function get($key)
    {
        return $key === 'id' ? $this->id : null;
    }

    public function remove(): bool
    {
        $this->removed = $this->removeResult;

        return $this->removeResult;
    }
}

final class FakeFieldForDelete
{
    public bool $saved = false;

    public function __construct(public mixed $section, private bool $saveResult = true)
    {
    }

    public function set($key, $value): void
    {
        if ($key === 'section') {
            $this->section = $value;
        }
    }

    public function save(): bool
    {
        $this->saved = true;

        return $this->saveResult;
    }
}
