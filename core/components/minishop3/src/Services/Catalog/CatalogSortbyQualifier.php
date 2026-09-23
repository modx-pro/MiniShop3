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
 * optional passthrough names (TVs, vendor_*) stay bare. Safe SQL functions (RAND, FIELD,
 * IFNULL, COALESCE, CAST) with validated arguments are kept (#757 review). Prefixed parts
 * are kept only for allowed table aliases (including keys from snippet leftJoin/innerJoin).
 */
final class CatalogSortbyQualifier
{
    private const SIMPLE_SORT_PART = '/^(?:`?(?P<table>[A-Za-z_][\w]*)`?\.)?`?(?P<field>[A-Za-z_][\w]*)`?(?P<dir>\s+(?:ASC|DESC))?$/i';

    private const SAFE_FUNCTION = '/^(?P<fn>RAND|FIELD|IFNULL|COALESCE|CAST)\s*\((?P<args>.*)\)(?P<dir>\s+(?:ASC|DESC))?$/is';

    /**
     * @param list<string> $resourceFieldNames Field names from modResource / msProduct
     * @param list<string> $dataFieldNames Field names from msProductData (qualified as Data.*)
     * @param list<string> $passthroughNames Bare names left as-is (TVs, vendor_*, option keys)
     * @param list<string> $allowedTableAliases When dropUnmatched, only these table prefixes pass
     * @param list<string>|null $droppedParts Optional out-list of rejected parts when the argument is passed
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
        ?array &$droppedParts = null,
    ): string {
        $collectDropped = \func_num_args() >= 9;
        $dropped = [];
        $trimmed = ltrim($sortby);
        if ($trimmed === '' || str_starts_with($trimmed, '{')) {
            if ($collectDropped) {
                $droppedParts = $dropped;
            }

            return $sortby;
        }

        if (!$dropUnmatched && str_contains($sortby, '(')) {
            if ($collectDropped) {
                $droppedParts = $dropped;
            }

            return $sortby;
        }

        $resourceFields = self::indexNames($resourceFieldNames);
        $dataFields = self::indexNames($dataFieldNames);
        $passthrough = self::indexNames($passthroughNames);
        $allowedTables = self::indexNames($allowedTableAliases);
        if ($resourceFields === [] && $dataFields === [] && $passthrough === []) {
            if ($collectDropped) {
                $droppedParts = $dropped;
            }

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
            if ($dropUnmatched) {
                $safeFn = self::qualifySafeFunctionPart(
                    $part,
                    $resourceFields,
                    $dataFields,
                    $passthrough,
                    $allowedTables,
                    $alias,
                    $dataAlias,
                );
                if ($safeFn !== null) {
                    $qualified[] = $safeFn;
                    continue;
                }
                if (!self::isAllowedSortPart(
                    $part,
                    $resourceFields,
                    $dataFields,
                    $passthrough,
                    $allowedTables,
                )) {
                    $dropped[] = $part;
                    continue;
                }
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

        if ($collectDropped) {
            $droppedParts = $dropped;
        }

        if ($qualified === []) {
            return $dropUnmatched ? $alias . '.id' : '';
        }

        return implode(', ', $qualified);
    }

    /**
     * Collect pdoTools join map keys as allowed ORDER BY table aliases (#757 review).
     *
     * @param array<string, mixed> ...$joinMaps
     * @return list<string>
     */
    public static function tableAliasesFromJoins(array ...$joinMaps): array
    {
        $aliases = [];
        foreach ($joinMaps as $map) {
            foreach (array_keys($map) as $key) {
                $key = trim((string) $key);
                if ($key !== '' && preg_match('/^[A-Za-z_][\w]*$/', $key)) {
                    $aliases[] = $key;
                }
            }
        }

        return array_values(array_unique($aliases));
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
        // Caller-supplied CASE / arbitrary expressions stay rejected; snippet injects those later.
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
     * Allowlisted SQL functions with validated / qualified arguments (#757 review).
     *
     * @param array<string, true> $resourceFields
     * @param array<string, true> $dataFields
     * @param array<string, true> $passthrough
     * @param array<string, true> $allowedTables
     */
    private static function qualifySafeFunctionPart(
        string $part,
        array $resourceFields,
        array $dataFields,
        array $passthrough,
        array $allowedTables,
        string $alias,
        string $dataAlias,
    ): ?string {
        if (!preg_match(self::SAFE_FUNCTION, trim($part), $match)) {
            return null;
        }

        $fn = strtoupper($match['fn']);
        $args = trim($match['args']);
        $dir = $match['dir'] ?? '';

        if ($fn === 'RAND') {
            return $args === '' ? 'RAND()' . $dir : null;
        }

        if ($args === '' || !self::isSafeFunctionArgs($args, $fn === 'CAST')) {
            return null;
        }

        if (!self::functionArgsUseOnlyAllowedIdentifiers(
            $args,
            $resourceFields,
            $dataFields,
            $passthrough,
            $allowedTables,
            $fn === 'CAST',
        )) {
            return null;
        }

        $qualifiedArgs = self::qualifyIdentifiersInExpression(
            $args,
            $resourceFields,
            $dataFields,
            $passthrough,
            $alias,
            $dataAlias,
            $fn === 'CAST',
        );

        return $fn . '(' . $qualifiedArgs . ')' . $dir;
    }

    private static function isSafeFunctionArgs(string $args, bool $allowCastAs): bool
    {
        // No nested calls, comments, or statement separators.
        if (str_contains($args, '(') || str_contains($args, ')')
            || str_contains($args, ';') || str_contains($args, '--')
            || str_contains($args, '/*') || str_contains($args, '*/')) {
            return false;
        }

        if (preg_match('/\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP|ALTER|INTO|SLEEP|BENCHMARK)\b/i', $args)) {
            return false;
        }

        // Strip string literals, then only identifiers / numbers / punctuation may remain.
        $stripped = preg_replace(
            '/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"/',
            ' ',
            $args,
        ) ?? $args;

        if ($allowCastAs) {
            $stripped = (string) preg_replace('/\bAS\b/i', ' ', $stripped);
        }

        return (bool) preg_match(
            '/^[\s,.`0-9A-Za-z_]+$/',
            $stripped,
        );
    }

    /**
     * @param array<string, true> $resourceFields
     * @param array<string, true> $dataFields
     * @param array<string, true> $passthrough
     * @param array<string, true> $allowedTables
     */
    private static function functionArgsUseOnlyAllowedIdentifiers(
        string $args,
        array $resourceFields,
        array $dataFields,
        array $passthrough,
        array $allowedTables,
        bool $allowCastAs,
    ): bool {
        $withoutStrings = preg_replace(
            '/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"/',
            ' ',
            $args,
        ) ?? $args;

        if ($allowCastAs) {
            $withoutStrings = (string) preg_replace('/\bAS\b/i', ' ', $withoutStrings);
        }

        if (!preg_match_all('/`?([A-Za-z_][\w]*)`?(?:\.`?([A-Za-z_][\w]*)`?)?/', $withoutStrings, $matches, PREG_SET_ORDER)) {
            return true;
        }

        foreach ($matches as $match) {
            $first = $match[1];
            $second = $match[2] ?? '';
            if ($second !== '') {
                if (!isset($allowedTables[strtolower($first)])) {
                    return false;
                }
                continue;
            }
            $lower = strtolower($first);
            // CAST type names (CHAR, SIGNED, …) and ASC/DESC never appear as lone first tokens
            // after AS strip for CAST; still allow common SQL type tokens.
            if ($allowCastAs && self::isSqlTypeToken($lower)) {
                continue;
            }
            if (!isset($resourceFields[$lower]) && !isset($dataFields[$lower]) && !isset($passthrough[$lower])) {
                return false;
            }
        }

        return true;
    }

    private static function isSqlTypeToken(string $lower): bool
    {
        return in_array($lower, [
            'char', 'varchar', 'binary', 'date', 'datetime', 'time', 'signed', 'unsigned',
            'decimal', 'integer', 'int', 'bigint', 'float', 'double', 'real', 'json',
        ], true);
    }

    /**
     * @param array<string, true> $resourceFields
     * @param array<string, true> $dataFields
     * @param array<string, true> $passthrough
     */
    private static function qualifyIdentifiersInExpression(
        string $args,
        array $resourceFields,
        array $dataFields,
        array $passthrough,
        string $alias,
        string $dataAlias,
        bool $allowCastAs,
    ): string {
        return (string) preg_replace_callback(
            '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")|(`?[A-Za-z_][\w]*`?(?:\.`?[A-Za-z_][\w]*`?)?)/',
            static function (array $m) use (
                $resourceFields,
                $dataFields,
                $passthrough,
                $alias,
                $dataAlias,
                $allowCastAs,
            ): string {
                if (($m[1] ?? '') !== '') {
                    return $m[1];
                }
                $token = $m[2];
                if (str_contains($token, '.')) {
                    return $token;
                }
                if ($allowCastAs && preg_match('/^AS$/i', $token)) {
                    return $token;
                }
                $bare = trim($token, '`');
                $lower = strtolower($bare);
                if ($allowCastAs && self::isSqlTypeToken($lower)) {
                    return $token;
                }
                // Same precedence as qualifySimplePart(): a declared TV, vendor field or
                // sortbyOptions key wins over a column of the same name, otherwise
                // FIELD(color, 'red', 'blue') would become FIELD(Data.color, …).
                if (isset($passthrough[$lower])) {
                    return $bare;
                }
                if (isset($resourceFields[$lower])) {
                    return $alias . '.' . $bare;
                }
                if (isset($dataFields[$lower])) {
                    return $dataAlias . '.' . $bare;
                }

                return $token;
            },
            $args,
        );
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

        // Explicitly declared names win over table columns: a TV, vendor field or
        // sortbyOptions key may be called `weight` or `color` just like a Data column,
        // and the caller means their own join, not msProductData (#742 / #757 review).
        if (isset($passthrough[$lower])) {
            return $field . $dir;
        }
        if (isset($resourceFields[$lower])) {
            return $alias . '.' . $field . $dir;
        }
        if (isset($dataFields[$lower])) {
            return $dataAlias . '.' . $field . $dir;
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
