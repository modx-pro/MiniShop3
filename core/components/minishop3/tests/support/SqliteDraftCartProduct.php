<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Support;

use MiniShop3\Model\msProduct;

/**
 * Lightweight published product for SQLite draft cart harnesses.
 */
final class SqliteDraftCartProduct extends msProduct
{
    /** @var array<string, mixed> */
    public $_fieldMeta = [];

    /** @var array<string, mixed> */
    protected $_fields = [];

    /** @var list<string> */
    protected $dataRelated = [];

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(array $fields = [])
    {
        $this->_fields = $fields;
    }

    public function get($k, $format = null, $formatTemplate = null)
    {
        return $this->_fields[$k] ?? null;
    }

    public function toArray($keyPrefix = '', $rawValues = false, $excludeLazy = false, $includeRelated = false): array
    {
        return $this->_fields;
    }

    public function getPrice($data = [])
    {
        return (float) ($this->_fields['price'] ?? 0);
    }

    public function getWeight($data = [])
    {
        return (float) ($this->_fields['weight'] ?? 0);
    }
}
