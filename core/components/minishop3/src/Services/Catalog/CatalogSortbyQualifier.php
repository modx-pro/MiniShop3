<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

/**
 * Qualify bare modResource columns in multi-field pdoTools sortby strings (#741 / #742).
 *
 * pdoTools qualifies a single field and JSON keys itself. A comma-separated string like
 * `pagetitle DESC, publishedon` stays bare. With the RG self-join on site_content that
 * makes ORDER BY ambiguous. Simple `field [ASC|DESC]` pieces get an `msProduct.` prefix
 * when the field exists on the resource; expressions with `(` (CASE, functions) are left alone.
 */
final class CatalogSortbyQualifier
{
    /**
     * @param list<string> $resourceFieldNames Field names from modResource / msProduct
     */
    public static function qualifyUnaliasedResourceFields(
        string $sortby,
        array $resourceFieldNames,
        string $alias = 'msProduct',
    ): string {
        $trimmed = ltrim($sortby);
        if ($trimmed === '' || str_starts_with($trimmed, '{') || str_contains($sortby, '(')) {
            return $sortby;
        }

        $fields = [];
        foreach ($resourceFieldNames as $name) {
            $name = strtolower(trim((string) $name));
            if ($name !== '') {
                $fields[$name] = true;
            }
        }
        if ($fields === []) {
            return $sortby;
        }

        $parts = array_map(static fn (string $part): string => trim($part), explode(',', $sortby));
        $qualified = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $qualified[] = self::qualifySimplePart($part, $fields, $alias);
        }

        return implode(', ', $qualified);
    }

    /**
     * @param array<string, true> $fields Lowercase field set
     */
    private static function qualifySimplePart(string $part, array $fields, string $alias): string
    {
        if (!preg_match(
            '/^(?:`?(?P<table>[A-Za-z_][\w]*)`?\.)?`?(?P<field>[A-Za-z_][\w]*)`?(?P<dir>\s+(?:ASC|DESC))?$/i',
            $part,
            $match
        )) {
            return $part;
        }

        if ($match['table'] !== '') {
            return $part;
        }

        $field = $match['field'];
        if (!isset($fields[strtolower($field)])) {
            return $part;
        }

        return $alias . '.' . $field . ($match['dir'] ?? '');
    }
}
