<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

use MiniShop3\Services\GridConfigService;
use MODX\Revolution\modX;

final class GridRelationColumnResolver
{
    /**
     * @param array<int, array<string, mixed>> $gridFields
     *
     * @return list<RelationColumnSpec>
     */
    public static function resolve(modX $modx, array $gridFields): array
    {
        /** @var GridConfigService|null $gridConfig */
        $gridConfig = $modx->services->get('ms3_grid_config');
        if (!$gridConfig) {
            return [];
        }

        $relationGroups = $gridConfig->extractRelationFields($gridFields);
        $out = [];

        foreach ($relationGroups as $group) {
            foreach ($group['fields'] as $fieldDef) {
                $spec = RelationColumnSpec::fromGroupField($group, $fieldDef);
                if ($spec !== null) {
                    $out[] = $spec;
                }
            }
        }

        return $out;
    }

    /**
     * Unique JOIN specs by alias (one JOIN per table+foreignKey group).
     *
     * @param list<RelationColumnSpec> $specs
     *
     * @return list<RelationColumnSpec>
     */
    public static function uniqueJoins(array $specs): array
    {
        $seen = [];
        $joins = [];

        foreach ($specs as $spec) {
            if (isset($seen[$spec->alias])) {
                continue;
            }
            $seen[$spec->alias] = true;
            $joins[] = $spec;
        }

        return $joins;
    }
}
