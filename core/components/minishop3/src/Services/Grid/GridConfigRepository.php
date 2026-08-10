<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

use MiniShop3\Model\msGridField;
use MODX\Revolution\modX;

/**
 * Persistence for msGridField rows (grid column config).
 */
final class GridConfigRepository
{
    public function __construct(private modX $modx)
    {
    }

    /**
     * @return list<msGridField>
     */
    public function findByGridKey(string $gridKey, bool $includeHidden = false): array
    {
        $where = ['grid_key' => $gridKey];
        if (!$includeHidden) {
            $where['visible'] = true;
        }

        return $this->collectSorted($where);
    }

    /**
     * @return list<msGridField>
     */
    public function findFilterableByGridKey(string $gridKey): array
    {
        return $this->collectSorted([
            'grid_key' => $gridKey,
            'filterable' => true,
        ]);
    }

    public function findOne(string $gridKey, string $fieldName): ?msGridField
    {
        /** @var msGridField|null $field */
        $field = $this->modx->getObject(msGridField::class, [
            'grid_key' => $gridKey,
            'field_name' => $fieldName,
        ]);

        return $field instanceof msGridField ? $field : null;
    }

    public function nextSortOrder(string $gridKey): int
    {
        $query = $this->modx->newQuery(msGridField::class);
        $query->where(['grid_key' => $gridKey]);
        $query->sortby('sort_order', 'DESC');
        $query->limit(1);
        $lastField = $this->modx->getObject(msGridField::class, $query);

        return $lastField ? (int) $lastField->get('sort_order') + 1 : 1;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): msGridField
    {
        /** @var msGridField $field */
        $field = $this->modx->newObject(msGridField::class);
        $field->fromArray($attributes);

        return $field;
    }

    public function save(msGridField $field): bool
    {
        return (bool) $field->save();
    }

    public function remove(msGridField $field): bool
    {
        return (bool) $field->remove();
    }

    /**
     * Non-system fields whose names are not in $fieldNamesToKeep (candidates for delete on save).
     *
     * Empty keep list returns [] — never wipe all non-system rows. Callers that omit every
     * field name must not treat that as "delete everything" (xPDO `NOT IN ()` is also invalid).
     *
     * @param list<string> $fieldNamesToKeep
     * @return list<msGridField>
     */
    public function findNonSystemNotIn(string $gridKey, array $fieldNamesToKeep): array
    {
        if ($fieldNamesToKeep === []) {
            return [];
        }

        /** @var list<msGridField> $fields */
        $fields = array_values($this->modx->getCollection(msGridField::class, [
            'grid_key' => $gridKey,
            'is_system' => false,
            'field_name:NOT IN' => $fieldNamesToKeep,
        ]) ?: []);

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeConfig(mixed $config): array
    {
        if (is_string($config)) {
            $decoded = json_decode($config, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function encodeConfig(array $config): string
    {
        return (string) json_encode($config, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $where
     * @return list<msGridField>
     */
    private function collectSorted(array $where): array
    {
        $query = $this->modx->newQuery(msGridField::class);
        $query->where($where);
        $query->sortby('sort_order', 'ASC');

        /** @var list<msGridField> $fields */
        $fields = array_values($this->modx->getCollection(msGridField::class, $query) ?: []);

        return $fields;
    }
}
