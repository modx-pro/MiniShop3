<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Unit\Services\Product;

use MiniShop3\Model\msLink;
use MiniShop3\Model\msProductLink;
use MiniShop3\Services\Product\ProductLinkService;
use MODX\Revolution\modX;
use PHPUnit\Framework\TestCase;

/**
 * create() must persist composite PK via set() and name save/type failures.
 */
final class ProductLinkServiceCreateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(modX::class, false)) {
            require_once dirname(__DIR__, 3) . '/stubs/ModxStub.php';
        }
    }

    public function testCreatePersistsCompositePrimaryKeys(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('one_to_many', $saved));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => true], $result);
        self::assertSame([
            ['link' => 1, 'master' => 22, 'slave' => 124],
        ], $saved);
    }

    public function testCreateOneToOneWritesBothDirections(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('one_to_one', $saved));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => true], $result);
        self::assertSame([
            ['link' => 1, 'master' => 22, 'slave' => 124],
            ['link' => 1, 'master' => 124, 'slave' => 22],
        ], $saved);
    }

    public function testCreateUnknownTypeReturnsNoLink(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('broken_type', $saved));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => false, 'message' => 'ms3_err_no_link'], $result);
        self::assertSame([], $saved);
    }

    public function testCreateSaveFailureReturnsLinkSave(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('one_to_many', $saved, false));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => false, 'message' => 'ms3_err_link_save'], $result);
    }

    public function testCreateManyToManyMeshesExistingSlaves(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('many_to_many', $saved, true, true, [22, 124]));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => true], $result);
        self::assertSame([
            ['link' => 1, 'master' => 22, 'slave' => 124],
            ['link' => 1, 'master' => 124, 'slave' => 22],
        ], $saved);
    }

    public function testCreateManyToManyQueryFailureReturnsLinkSave(): void
    {
        $saved = [];
        $service = new ProductLinkService($this->modxForCreate('many_to_many', $saved, true, false));

        $result = $service->create(22, 124, 1);

        self::assertSame(['ok' => false, 'message' => 'ms3_err_link_save'], $result);
        self::assertSame([
            ['link' => 1, 'master' => 22, 'slave' => 124],
            ['link' => 1, 'master' => 124, 'slave' => 22],
        ], $saved);
    }

    public function testLinkSaveLexiconExistsInEnAndRu(): void
    {
        $lexiconDir = dirname(__DIR__, 4) . '/lexicon';
        $en = (string) file_get_contents($lexiconDir . '/en/default.inc.php');
        $ru = (string) file_get_contents($lexiconDir . '/ru/default.inc.php');

        self::assertStringContainsString("\$_lang['ms3_err_link_save']", $en);
        self::assertStringContainsString("\$_lang['ms3_err_link_save']", $ru);
    }

    /**
     * @param list<array{link: int, master: int, slave: int}> $saved
     * @param list<int> $meshSlaves
     */
    private function modxForCreate(
        string $type,
        array &$saved,
        bool $saveOk = true,
        bool $meshQueryOk = true,
        array $meshSlaves = [],
    ): modX {
        $link = new class ($type) extends msLink {
            public function __construct(private string $linkType)
            {
                parent::__construct();
            }

            public function get($key)
            {
                return $key === 'type' ? $this->linkType : null;
            }
        };

        return new class ($link, $saved, $saveOk, $meshQueryOk, $meshSlaves) extends modX {
            /**
             * @param list<array{link: int, master: int, slave: int}> $saved
             * @param list<int> $meshSlaves
             */
            public function __construct(
                private object $link,
                private array &$saved,
                private bool $saveOk,
                private bool $meshQueryOk,
                private array $meshSlaves,
            ) {
                parent::__construct();
            }

            public function getObject($className, $criteria = null, $cacheFlag = true)
            {
                if ($className === msLink::class) {
                    return $this->link;
                }

                if ($className === msProductLink::class && is_array($criteria)) {
                    foreach ($this->saved as $row) {
                        if (
                            $row['link'] === (int) ($criteria['link'] ?? 0)
                            && $row['master'] === (int) ($criteria['master'] ?? 0)
                            && $row['slave'] === (int) ($criteria['slave'] ?? 0)
                        ) {
                            return new \stdClass();
                        }
                    }
                }

                return null;
            }

            public function newObject($className, $fields = [])
            {
                return new ProductLinkSaveProbe($this->saved, $this->saveOk);
            }

            public function newQuery($className, $criteria = null, $cacheFlag = true)
            {
                $ok = $this->meshQueryOk;
                $slaves = $this->meshSlaves;

                return new class ($ok, $slaves) {
                    public mixed $stmt = null;

                    public function __construct(
                        private bool $queryOk,
                        private array $slaves,
                    ) {
                    }

                    public function andCondition($criteria, $conjunction = null): self
                    {
                        return $this;
                    }

                    public function select($columns): self
                    {
                        return $this;
                    }

                    public function prepare(): bool
                    {
                        if (!$this->queryOk) {
                            return false;
                        }

                        $this->stmt = new class ($this->slaves) {
                            public function __construct(private array $slaves)
                            {
                            }

                            public function execute(): bool
                            {
                                return true;
                            }

                            public function fetchAll($mode = null): array
                            {
                                return $this->slaves;
                            }
                        };

                        return true;
                    }
                };
            }
        };
    }
}

/**
 * Records composite PK fields. fromArray() skips PK keys unless $setPrimaryKeys (xPDO).
 */
final class ProductLinkSaveProbe
{
    /** @var array<string, mixed> */
    private array $fields = [];

    /**
     * @param list<array{link: int, master: int, slave: int}> $saved
     */
    public function __construct(
        private array &$saved,
        private bool $saveOk,
    ) {
    }

    public function set($key, $value = null): bool
    {
        $this->fields[(string) $key] = $value;

        return true;
    }

    public function fromArray($fields, $keyPrefix = '', $setPrimaryKeys = false): bool
    {
        $pk = ['link', 'master', 'slave'];
        foreach ((array) $fields as $key => $value) {
            if (!$setPrimaryKeys && in_array((string) $key, $pk, true)) {
                continue;
            }
            $this->fields[(string) $key] = $value;
        }

        return true;
    }

    public function save(): bool
    {
        $row = [
            'link' => (int) ($this->fields['link'] ?? 0),
            'master' => (int) ($this->fields['master'] ?? 0),
            'slave' => (int) ($this->fields['slave'] ?? 0),
        ];
        if (!$this->saveOk || $row['link'] <= 0 || $row['master'] <= 0 || $row['slave'] <= 0) {
            return false;
        }

        $this->saved[] = $row;

        return true;
    }
}
