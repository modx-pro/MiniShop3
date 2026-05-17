<?php

declare(strict_types=1);

namespace MiniShop3\Services\Grid;

final class GridOptionColumnResolver
{
    /**
     * @param array<int, array<string, mixed>> $gridFields
     *
     * @return list<OptionColumnSpec>
     */
    public static function resolve(array $gridFields): array
    {
        $out = [];
        foreach ($gridFields as $field) {
            $spec = OptionColumnSpec::tryFromGridField($field);
            if ($spec !== null) {
                $out[] = $spec;
            }
        }

        return $out;
    }
}
