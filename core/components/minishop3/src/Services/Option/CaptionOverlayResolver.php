<?php

declare(strict_types=1);

namespace MiniShop3\Services\Option;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msProduct;
use xPDO\xPDO;

/**
 * Resolves effective option caption/description with per-category overrides (issue #200 / PR #203).
 */
class CaptionOverlayResolver
{
    /** @var xPDO */
    protected $xpdo;

    public function __construct(xPDO $xpdo)
    {
        $this->xpdo = $xpdo;
    }

    /**
     * Non-empty override wins; null or whitespace-only string inherits global.
     */
    public function mergeCaptionDescription(?string $override, string $global): string
    {
        $trimmed = $override !== null ? trim($override) : '';

        return $trimmed !== '' ? $trimmed : $global;
    }

    /**
     * Product category membership and parent id in one pass (single msProduct load).
     *
     * Policy (issue #200): overrides are resolved using links in these categories; tie-breaking
     * for the same option across categories uses {@see pickWinningCategoryOptionLink()}.
     *
     * @return array{category_ids: int[], parent_category_id: int}
     */
    public function getProductCategoriesContext(
        int $productId,
        ?int $fallbackParentId = null,
        ?int $knownParentCategoryId = null
    ): array {
        $memberIds = [];
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $productId]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            $memberIds = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        $parentCategoryId = 0;
        if ($knownParentCategoryId !== null && $knownParentCategoryId > 0) {
            $parentCategoryId = (int)$knownParentCategoryId;
        } else {
            $product = $this->xpdo->getObject(msProduct::class, $productId);
            if ($product) {
                $parentCategoryId = (int)$product->get('parent');
            } elseif ($fallbackParentId !== null) {
                $parentCategoryId = (int)$fallbackParentId;
            }
        }

        $ids = $memberIds;
        if ($parentCategoryId > 0) {
            $ids[] = $parentCategoryId;
        }

        return [
            'category_ids' => $this->normalizePositiveIds($ids),
            'parent_category_id' => $parentCategoryId,
        ];
    }

    /**
     * Category ids where the product belongs: additional members plus direct parent.
     *
     * @return int[]
     */
    public function getProductCategoryIdList(int $productId, ?int $fallbackParentId = null): array
    {
        return $this->getProductCategoriesContext($productId, $fallbackParentId)['category_ids'];
    }

    /**
     * When the same option is linked in several product categories with different overrides,
     * pick one row: parent category first, then lowest msCategoryOption.position among remaining,
     * then lowest category_id (stable tie-break; aligns with issue #200).
     *
     * @param array<int, array<string, mixed>> $linkRows
     * @return array<string, mixed>|null
     */
    public function pickWinningCategoryOptionLink(array $linkRows, int $parentCategoryId): ?array
    {
        if ($linkRows === []) {
            return null;
        }

        usort($linkRows, static function ($a, $b) use ($parentCategoryId) {
            $aParent = ((int)$a['category_id'] === $parentCategoryId) ? 0 : 1;
            $bParent = ((int)$b['category_id'] === $parentCategoryId) ? 0 : 1;
            if ($aParent !== $bParent) {
                return $aParent <=> $bParent;
            }
            if ((int)$a['position'] !== (int)$b['position']) {
                return (int)$a['position'] <=> (int)$b['position'];
            }

            return (int)$a['category_id'] <=> (int)$b['category_id'];
        });

        return $linkRows[0];
    }

    /**
     * @param array<int, array{caption: string, description: string}> $globalsByOptionId
     * @return array<int, array{caption: string, description: string}>
     */
    public function resolveEffectiveCaptionDescriptionByOptionIds(
        int $productId,
        array $globalsByOptionId,
        ?int $knownParentCategoryId = null
    ): array {
        if ($globalsByOptionId === []) {
            return [];
        }

        $ctx = $this->getProductCategoriesContext($productId, null, $knownParentCategoryId);
        if ($ctx['category_ids'] === []) {
            return $globalsByOptionId;
        }

        $linksByOptionId = $this->loadCategoryOptionLinksGrouped(
            array_keys($globalsByOptionId),
            $ctx['category_ids']
        );

        return $this->resolveEffectiveCaptionDescriptionWithContext($globalsByOptionId, $ctx, $linksByOptionId);
    }

    /**
     * @param array<int, array{caption: string, description: string}> $globalsByOptionId
     * @param array{category_ids: int[], parent_category_id: int} $context
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionId
     * @return array<int, array{caption: string, description: string}>
     */
    public function resolveEffectiveCaptionDescriptionWithContext(
        array $globalsByOptionId,
        array $context,
        array $linksByOptionId
    ): array {
        if ($globalsByOptionId === [] || $context['category_ids'] === []) {
            return $globalsByOptionId;
        }

        $parentCategoryId = (int)$context['parent_category_id'];
        $out = [];
        foreach ($globalsByOptionId as $optionId => $global) {
            $caption = $global['caption'];
            $description = $global['description'];
            $links = $linksByOptionId[$optionId] ?? [];
            if ($links !== []) {
                $winner = $this->pickWinningCategoryOptionLink($links, $parentCategoryId);
                if ($winner !== null) {
                    $caption = $this->mergeCaptionDescription(
                        isset($winner['caption']) ? (string)$winner['caption'] : null,
                        $caption
                    );
                    $description = $this->mergeCaptionDescription(
                        isset($winner['description']) ? (string)$winner['description'] : null,
                        $description
                    );
                }
            }
            $out[(int)$optionId] = [
                'caption' => $caption,
                'description' => $description,
            ];
        }

        return $out;
    }

    /**
     * @param int[] $optionIds
     * @param int[] $categoryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function loadCategoryOptionLinksGrouped(array $optionIds, array $categoryIds): array
    {
        if ($optionIds === [] || $categoryIds === []) {
            return [];
        }

        $c = $this->xpdo->newQuery(msCategoryOption::class, [
            'option_id:IN' => $optionIds,
            'category_id:IN' => $categoryIds,
        ]);
        $c->select($this->xpdo->getSelectColumns(msCategoryOption::class, 'msCategoryOption'));

        $linksByOptionId = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $linksByOptionId[(int)$row['option_id']][] = $row;
            }
        }

        return $linksByOptionId;
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionId
     * @param int[] $categoryIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function filterCategoryOptionLinksByCategories(array $linksByOptionId, array $categoryIds): array
    {
        if ($linksByOptionId === [] || $categoryIds === []) {
            return [];
        }

        $allowed = array_flip($categoryIds);
        $out = [];
        foreach ($linksByOptionId as $oid => $rows) {
            $filtered = array_values(array_filter(
                $rows,
                static function (array $row) use ($allowed): bool {
                    return isset($allowed[(int)$row['category_id']]);
                }
            ));
            if ($filtered !== []) {
                $out[$oid] = $filtered;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey
     * @param array{category_ids: int[], parent_category_id: int} $context
     * @param array<int, array<int, array<string, mixed>>> $linksByOptionIdForProduct
     * @return array<string, array{caption: string, description: string}>
     */
    public function buildCaptionDescriptionOverlayFromRowsAndLinks(
        array $lastRowByOptionKey,
        array $context,
        array $linksByOptionIdForProduct
    ): array {
        [$globalsByOptionId, $keyByOptionId] = $this->extractGlobalsAndKeyMap($lastRowByOptionKey);

        return $this->mapEffectiveByIdToOptionKeys(
            $keyByOptionId,
            $this->resolveEffectiveCaptionDescriptionWithContext(
                $globalsByOptionId,
                $context,
                $linksByOptionIdForProduct
            )
        );
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey Last SQL row per option key (includes msOption.id, caption, description)
     * @return array<string, array{caption: string, description: string}>
     */
    public function buildCaptionDescriptionOverlayForProduct(int $productId, array $lastRowByOptionKey): array
    {
        [$globalsByOptionId, $keyByOptionId] = $this->extractGlobalsAndKeyMap($lastRowByOptionKey);

        return $this->mapEffectiveByIdToOptionKeys(
            $keyByOptionId,
            $this->resolveEffectiveCaptionDescriptionByOptionIds($productId, $globalsByOptionId)
        );
    }

    /**
     * @param array<string, array<string, mixed>> $lastRowByOptionKey
     * @return array{0: array<int, array{caption: string, description: string}>, 1: array<int, string>}
     */
    public function extractGlobalsAndKeyMap(array $lastRowByOptionKey): array
    {
        $globalsByOptionId = [];
        $keyByOptionId = [];
        foreach ($lastRowByOptionKey as $key => $row) {
            $oid = (int)($row['id'] ?? 0);
            if ($oid < 1) {
                continue;
            }
            $globalsByOptionId[$oid] = [
                'caption' => (string)($row['caption'] ?? ''),
                'description' => (string)($row['description'] ?? ''),
            ];
            $keyByOptionId[$oid] = $key;
        }

        return [$globalsByOptionId, $keyByOptionId];
    }

    /**
     * @param array<int, string> $keyByOptionId
     * @param array<int, array{caption: string, description: string}> $effectiveById
     * @return array<string, array{caption: string, description: string}>
     */
    protected function mapEffectiveByIdToOptionKeys(array $keyByOptionId, array $effectiveById): array
    {
        $out = [];
        foreach ($keyByOptionId as $oid => $key) {
            if (isset($effectiveById[$oid])) {
                $out[$key] = $effectiveById[$oid];
            }
        }

        return $out;
    }

    /**
     * @param array<int|string> $ids
     * @return int[]
     */
    protected function normalizePositiveIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static function ($id) {
                return $id > 0;
            }
        )));
    }
}
