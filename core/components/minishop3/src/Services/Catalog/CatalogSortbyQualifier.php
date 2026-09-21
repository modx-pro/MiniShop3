<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

/**
 * Qualify bare columns in multi-field pdoTools sortby strings (#741 / #742 / #755).
 *
 * pdoTools qualifies a single field and JSON keys itself. A comma-separated string like
 * `pagetitle DESC, publishedon` stays bare. With the RG self-join on site_content that
 * makes ORDER BY ambiguous.
 *
 * Snippet mode ($dropUnmatched): drop unknown *simple* parts before menuindex / sortbyOptions
 * inject expressions. Known resource fields → msProduct.*; known Data fields → Data.*;
 * optional passthrough names (TVs, vendor_*) stay bare. Parenthetical / CASE input from the
 * caller is rejected in drop mode (expressions are injected by the snippet afterwards).
 * Prefixed parts are kept only for allowed table aliases (not arbitrary table.field).
 */
final class CatalogSortbyQualifier
{
    private const SIMPLE_SORT_PART = '/^(?:`?(?P<table>[A-Za-z_][\w]*)`?\.)?`?(?P<field>[A-Za-z_][\w]*)`?(?P<dir>\s+(?:ASC|DESC))?$/i';

    /**
     * @param list<string> $resourceFieldNames Field names from modResource / msProduct
     * @param list<string> $dataFieldNames Field names from msProductData (qualified as Data.*)
     * @param list<string> $passthroughNames Bare names left as-is (TVs, vendor_*, option keys)
     * @param list<string> $allowedTableAliases When dropUnmatched, only these table prefixes pass
     */
    public static function qualifyUnaliasedResourceFields(
        string $sortby,
        array $resourceFieldNames,
        string $alias = 'msProduct',
        bool $dropUnmatched = false,
        array $dataFieldNames = [],
        string $dataAlias = 'Data',
        array $passthroughNames = [],
        array $allowedTableAliases = ['msProduct', 'Data', 'Vendor'],
    ): string {
        $trimmed = ltrim($sortby);
        if ($trimmed === '' || str_starts_with($trimmed, '{')) {
            return $sortby;
        }

        if (!$dropUnmatched && str_contains($sortby, '(')) {
            return $sortby;
        }

        $resourceFields = self::indexNames($resourceFieldNames);
        $dataFields = self::indexNames($dataFieldNames);
        $passthrough = self::indexNames($passthroughNames);
        $allowedTables = self::indexNames($allowedTableAliases);
        if ($resourceFields === [] && $dataFields === [] && $passthrough === []) {
            return $sortby;
        }

        $parts = $dropUnmatched
            ? self::splitSortParts($sortby)
            : array_map(static fn (string $part): string => trim($part), explode(',', $sortby));
        $qualified = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($dropUnmatched && !self::isAllowedSortPart(
                $part,
                $resourceFields,
                $dataFields,
                $passthrough,
                $allowedTables,
            )) {
                continue;
            }
            $qualified[] = self::qualifySimplePart(
                $part,
                $resourceFields,
                $dataFields,
                $passthrough,
                $alias,
                $dataAlias,
            );
        }

        if ($qualified === []) {
            return $dropUnmatched ? $alias . '.id' : '';
        }

        return implode(', ', $qualified);
    }

    /**
     * @param list<string> $names
     * @return array<string, true>
     */
    private static function indexNames(array $names): array
    {
        $indexed = [];
        foreach ($names as $name) {
            $name = strtolower(trim((string) $name));
            if ($name !== '') {
                $indexed[$name] = true;
            }
        }

        return $indexed;
    }

    /**
     * @return list<string>
     */
    private static function splitSortParts(string $sortby): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($sortby);

        for ($i = 0; $i < $length; $i++) {
            $char = $sortby[$i];
            if ($char === '(') {
                ++$depth;
                $current .= $char;
            } elseif ($char === ')') {
                --$depth;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $parts[] = $trimmed;
        }

        return $parts;
    }

    /**
     * @param array<string, true> $resourceFields
     * @param array<string, true> $dataFields
     * @param array<string, true> $passthrough
     * @param array<string, true> $allowedTables
     */
    private static function isAllowedSortPart(
        string $part,
        array $resourceFields,
        array $dataFields,
        array $passthrough,
        array $allowedTables,
    ): bool {
        // Caller-supplied expressions are rejected in drop mode; snippet injects CASE/CAST later.
        if (str_contains($part, '(') || preg_match('/^CASE\s+/i', trim($part))) {
            return false;
        }

        $match = self::matchSimpleSortPart($part);
        if ($match === null) {
            return false;
        }

        if ($match['table'] !== '') {
            return isset($allowedTables[strtolower($match['table'])]);
        }

        $field = strtolower($match['field']);

        return isset($resourceFields[$field])
            || isset($dataFields[$field])
            || isset($passthrough[$field]);
    }

    /**
     * @param array<string, true> $resourceFields
     * @param array<string, true> $dataFields
     * @param array<string, true> $passthrough
     */
    private static function qualifySimplePart(
        string $part,
        array $resourceFields,
        array $dataFields,
        array $passthrough,
        string $alias,
        string $dataAlias,
    ): string {
        $match = self::matchSimpleSortPart($part);
        if ($match === null || $match['table'] !== '') {
            return $part;
        }

        $field = $match['field'];
        $lower = strtolower($field);
        $dir = $match['dir'] ?? '';

        if (isset($resourceFields[$lower])) {
            return $alias . '.' . $field . $dir;
        }
        if (isset($dataFields[$lower])) {
            return $dataAlias . '.' . $field . $dir;
        }
        if (isset($passthrough[$lower])) {
            return $field . $dir;
        }

        return $part;
    }

    /**
     * @return array{table: string, field: string, dir?: string}|null
     */
    private static function matchSimpleSortPart(string $part): ?array
    {
        if (!preg_match(self::SIMPLE_SORT_PART, $part, $match)) {
            return null;
        }

        return $match;
    }
}
