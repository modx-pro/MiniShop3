<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

use MODX\Revolution\modX;

require_once __DIR__ . '/ModxStub.php';

final class CatalogAclInvalidatorModxStub extends modX
{
    public object $cacheManager;

    /** @param array<string, mixed> $options */
    public function __construct(
        object $cacheManager,
        object $services,
        private array $options,
        /** @var list<string> $contextKeys */
        private array $contextKeys,
    ) {
        parent::__construct();
        $this->cacheManager = $cacheManager;
        $this->services = $services;
    }

    public function getOption(string $key, $options = null, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function newQuery(string $class)
    {
        return new CatalogAclInvalidatorContextQueryStub($this->contextKeys);
    }

    public function escape(string $value): string
    {
        return '`' . $value . '`';
    }

    public function log($level, $msg): void
    {
    }
}

final class CatalogAclInvalidatorContextQueryStub
{
    public object $stmt;

    /** @param list<string> $contexts */
    public function __construct(private array $contexts)
    {
        $this->stmt = new class($contexts) {
            /** @param list<string> $contexts */
            public function __construct(private array $contexts)
            {
            }

            public function execute(): bool
            {
                return true;
            }

            public function fetchAll(int $mode): array
            {
                return $this->contexts;
            }
        };
    }

    public function select(string $field): self
    {
        return $this;
    }

    public function where(array $criteria): self
    {
        return $this;
    }

    public function prepare(): bool
    {
        return true;
    }
}
