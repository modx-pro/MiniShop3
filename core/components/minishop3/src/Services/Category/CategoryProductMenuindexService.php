<?php

declare(strict_types=1);

namespace MiniShop3\Services\Category;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;
use xPDO\Om\xPDOQuery;

/**
 * Per-category menuindex: native parent uses resource menuindex, member links use msCategoryMember.menuindex (#625).
 */
final class CategoryProductMenuindexService
{
    public const MEMBER_JOIN_ALIAS = 'CategoryMember';

    public function __construct(private modX $modx)
    {
    }

    public static function isNativeInCategory(int $productParent, int $categoryId): bool
    {
        return $productParent === $categoryId;
    }

    /**
     * @param list<int> $categoryIds
     */
    public static function isNativeInCategories(int $productParent, array $categoryIds): bool
    {
        return in_array($productParent, $categoryIds, true);
    }

    /**
     * Effective sort/select expression for a single category grid context.
     *
     * Identifiers are backtick-quoted: msProducts passes this expression as pdoTools
     * sortby, and Fetch::addSort() splits it on commas and rewrites every `alias.field `
     * piece that has no backtick, which breaks the CASE/COALESCE into invalid SQL.
     */
    public static function effectiveMenuindexSql(
        int $categoryId,
        string $productAlias = 'msProduct',
        string $memberAlias = self::MEMBER_JOIN_ALIAS,
    ): string {
        return self::effectiveMenuindexCase(
            self::column($productAlias, 'parent') . ' = ' . max(0, $categoryId),
            self::column($memberAlias, 'menuindex'),
            $productAlias,
        );
    }

    /**
     * Effective sort/select when multiple category IDs define the catalog scope.
     *
     * @param list<int> $categoryIds
     */
    public static function effectiveMenuindexSqlForCategories(
        array $categoryIds,
        string $productAlias = 'msProduct',
        string $memberAlias = self::MEMBER_JOIN_ALIAS,
    ): string {
        $categoryIds = self::normalizeCategoryIds($categoryIds);
        if ($categoryIds === []) {
            return self::column($productAlias, 'menuindex');
        }

        if (count($categoryIds) === 1) {
            return self::effectiveMenuindexSql($categoryIds[0], $productAlias, $memberAlias);
        }

        return self::effectiveMenuindexCase(
            self::column($productAlias, 'parent') . ' IN (' . implode(',', $categoryIds) . ')',
            'MIN(' . self::column($memberAlias, 'menuindex') . ')',
            $productAlias,
        );
    }

    /**
     * Replace the product menuindex column in a pdoTools sortby (plain, JSON, or array) with the
     * effective per-category expression for msProducts.
     *
     * Matches `msProduct.menuindex` (every occurrence) or a bare `menuindex` (first one), with
     * or without backticks. A menuindex qualified by another alias (e.g. CategoryMember.menuindex)
     * is left alone. Unchanged when there is no category scope or the sortby is already substituted.
     *
     * Arrays (Fenom/PHP) are normalized to JSON before substitution so `(string)` cast is never
     * used — that would turn `['menuindex' => 'ASC']` into `"Array"` and skip the CASE (#740).
     *
     * @param string|array<string, string> $sortby
     * @param list<int> $categoryIds
     */
    public static function substituteMenuindexSortby(string|array $sortby, array $categoryIds): string
    {
        $sortby = self::normalizeSortbyInput($sortby);
        $categoryIds = self::normalizeCategoryIds($categoryIds);
        if ($categoryIds === [] || str_contains($sortby, 'CASE WHEN')) {
            return $sortby;
        }

        $effectiveSql = self::effectiveMenuindexSqlForCategories($categoryIds);
        $aliased = '/`?\bmsProduct`?\.`?menuindex\b`?/i';
        if (preg_match($aliased, $sortby)) {
            return (string) preg_replace($aliased, $effectiveSql, $sortby);
        }

        return (string) preg_replace('/(?<![\w.`])`?menuindex\b`?/i', $effectiveSql, $sortby, 1);
    }

    /**
     * Whether sortby (string, JSON, or array) refers to menuindex for JOIN gating in msProducts.
     *
     * @param string|array<string, string>|null $sortby
     */
    public static function sortbyRefersToMenuindex(string|array|null $sortby): bool
    {
        if ($sortby === null || $sortby === '' || $sortby === []) {
            return false;
        }

        $normalized = self::normalizeSortbyInput($sortby);

        return $normalized !== '' && (bool) preg_match('/\bmenuindex\b/i', $normalized);
    }

    /**
     * @param string|array<string, string> $sortby
     */
    private static function normalizeSortbyInput(string|array $sortby): string
    {
        if (is_array($sortby)) {
            $json = json_encode($sortby, JSON_UNESCAPED_UNICODE);

            return is_string($json) ? $json : '';
        }

        return $sortby;
    }

    public static function memberJoinOn(int $categoryId, string $memberAlias = self::MEMBER_JOIN_ALIAS): string
    {
        $categoryId = max(0, $categoryId);

        return "`{$memberAlias}`.product_id = msProduct.id AND `{$memberAlias}`.category_id = {$categoryId}";
    }

    /**
     * @param list<int> $categoryIds
     */
    public static function memberJoinOnCategories(array $categoryIds, string $memberAlias = self::MEMBER_JOIN_ALIAS): string
    {
        $categoryIds = self::normalizeCategoryIds($categoryIds);
        if ($categoryIds === []) {
            return '1=0';
        }

        if (count($categoryIds) === 1) {
            return self::memberJoinOn($categoryIds[0], $memberAlias);
        }

        return "`{$memberAlias}`.product_id = msProduct.id AND `{$memberAlias}`.category_id IN ("
            . implode(',', $categoryIds)
            . ')';
    }

    /**
     * LEFT JOIN msCategoryMember for catalog/list scope and return effective menuindex SQL.
     *
     * @param list<int> $categoryIds
     */
    public static function applyMemberJoin(xPDOQuery $query, array $categoryIds): string
    {
        $memberAlias = self::MEMBER_JOIN_ALIAS;
        $query->leftJoin(
            msCategoryMember::class,
            $memberAlias,
            self::memberJoinOnCategories($categoryIds, $memberAlias)
        );

        return self::effectiveMenuindexSqlForCategories($categoryIds);
    }

    /**
     * @param list<int> $categoryIds
     * @return list<int>
     */
    private static function normalizeCategoryIds(array $categoryIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
    }

    private static function effectiveMenuindexCase(
        string $nativeParentCondition,
        string $memberMenuindexExpression,
        string $productAlias,
    ): string {
        $productMenuindex = self::column($productAlias, 'menuindex');

        return 'CASE WHEN '
            . "{$nativeParentCondition} "
            . "THEN {$productMenuindex} "
            . "ELSE COALESCE({$memberMenuindexExpression}, {$productMenuindex}) "
            . 'END';
    }

    private static function column(string $alias, string $column): string
    {
        return "`{$alias}`.`{$column}`";
    }

    public function getNextMemberMenuindex(int $categoryId): int
    {
        if ($categoryId <= 0) {
            return 0;
        }

        $maxNative = $this->fetchMaxMenuindex(
            msProduct::class,
            ['parent' => $categoryId],
        );
        $maxMember = $this->fetchMaxMenuindex(
            msCategoryMember::class,
            ['category_id' => $categoryId],
        );

        return max($maxNative, $maxMember) + 1;
    }

    public function ensureMember(int $productId, int $categoryId): bool
    {
        if ($productId <= 0 || $categoryId <= 0) {
            return false;
        }

        $existing = $this->modx->getObject(msCategoryMember::class, [
            'category_id' => $categoryId,
            'product_id' => $productId,
        ]);
        if ($existing) {
            return true;
        }

        /** @var msCategoryMember $member */
        $member = $this->modx->newObject(msCategoryMember::class);
        $member->set('product_id', $productId);
        $member->set('category_id', $categoryId);
        $member->set('menuindex', $this->getNextMemberMenuindex($categoryId));

        return (bool) $member->save();
    }

    /**
     * @param class-string $classKey
     * @param array<string, mixed> $criteria
     */
    private function fetchMaxMenuindex(string $classKey, array $criteria): int
    {
        $query = $this->modx->newQuery($classKey);
        $query->where($criteria);
        $query->select('MAX(menuindex)');

        if (!$query->prepare() || !$query->stmt->execute()) {
            return -1;
        }

        $max = $query->stmt->fetchColumn();

        return $max !== false ? (int) $max : -1;
    }

    public function setMenuindexInCategory(int $productId, int $categoryId, int $menuindex): bool
    {
        if ($productId <= 0 || $categoryId <= 0) {
            return false;
        }

        /** @var msProduct|null $product */
        $product = $this->modx->getObject(msProduct::class, $productId);
        if (!$product) {
            return false;
        }

        if (self::isNativeInCategory((int) $product->get('parent'), $categoryId)) {
            $product->set('menuindex', $menuindex);

            return (bool) $product->save();
        }

        /** @var msCategoryMember|null $member */
        $member = $this->modx->getObject(msCategoryMember::class, [
            'category_id' => $categoryId,
            'product_id' => $productId,
        ]);
        if (!$member) {
            return false;
        }

        $member->set('menuindex', $menuindex);

        return (bool) $member->save();
    }
}
