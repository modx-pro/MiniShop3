<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Utils\ResourceCategoryTreeQueryTrait;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

/**
 * Lazy tree nodes for the product Categories tab (Vue manager).
 *
 * Node visibility matches the options category tree (navigation containers + msCategory).
 * Membership and locked parent rules mirror {@see \MiniShop3\Processors\Category\GetNodes}.
 */
class ProductCategoryTreeService
{
    use ResourceCategoryTreeQueryTrait;

    public function __construct(private modX $modx)
    {
    }

    /**
     * @param int $parent Parent resource id (0 = tree roots for current context)
     * @param int $productId Product being edited
     * @param int $parentCategoryId Product parent resource (always checked, not removable)
     * @param array<int|string> $preChecked Optional ids from the client hidden field
     * @param bool $clientSentCategories When true, checked state follows preChecked only (not stale DB membership)
     * @return list<array<string, mixed>>
     */
    public function getTreeNodes(
        int $parent,
        int $productId,
        int $parentCategoryId,
        array $preChecked = [],
        bool $clientSentCategories = false
    ): array
    {
        $checkedSet = $this->buildCheckedSet($parentCategoryId, $preChecked);
        $treeClassKeysSql = $this->quoteSqlStringList($this->getTreeClassKeys());
        $categoryClassKeysSql = $this->quoteSqlStringList($this->treeCategoryClassKeys());
        $treeNodeWhere = $this->getTreeNodeSqlFilter('modResource', $treeClassKeysSql, $categoryClassKeysSql);
        $childNodeWhere = $this->getTreeNodeSqlFilter('Child', $treeClassKeysSql, $categoryClassKeysSql);

        $c = $this->modx->newQuery(modResource::class);
        $c->leftJoin(
            modResource::class,
            'Child',
            "`modResource`.`id` = `Child`.`parent` AND `Child`.`deleted` = 0 AND {$childNodeWhere}"
        );
        if ($productId > 0) {
            $c->leftJoin(
                msCategoryMember::class,
                'Member',
                [
                    'modResource.id = Member.category_id',
                    'Member.product_id' => $productId,
                ]
            );
            $c->select('Member.category_id AS member');
        }
        $c->select($this->modx->getSelectColumns(modResource::class, 'modResource', '', [
            'id',
            'pagetitle',
            'menutitle',
            'parent',
            'published',
            'hidemenu',
            'class_key',
        ]));
        $c->select('COUNT(Child.id) AS childrenCount');
        $c->where([
            'modResource.parent' => $parent,
            'modResource.deleted' => 0,
            'modResource.show_in_tree' => true,
        ]);
        $c->where($treeNodeWhere);
        $c->groupby('modResource.id');
        $c->sortby('modResource.menuindex', 'ASC');

        $nodes = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int)$row['id'];
                $selectable = $this->isCategoryClass((string)$row['class_key']);
                $checked = $selectable && (
                    $clientSentCategories
                        ? isset($checkedSet[$id])
                        : (!empty($row['member']) || isset($checkedSet[$id]))
                );
                $nodes[] = [
                    'id' => $id,
                    'label' => (string)($row['menutitle'] ?: $row['pagetitle'] ?? ''),
                    'leaf' => (int)$row['childrenCount'] === 0,
                    'checked' => $checked,
                    'selectable' => $selectable,
                    'locked' => $selectable && $id === $parentCategoryId,
                    'class_key' => $row['class_key'],
                    'published' => (int)$row['published'],
                    'hidemenu' => (int)($row['hidemenu'] ?? 0),
                ];
            }
        }

        return $nodes;
    }

    /**
     * @param array<int|string> $preChecked
     * @return array<int, true>
     */
    private function buildCheckedSet(int $parentCategoryId, array $preChecked): array
    {
        $checkedSet = [];
        foreach ($preChecked as $catId) {
            if (is_numeric($catId)) {
                $checkedSet[(int)$catId] = true;
            }
        }
        if ($parentCategoryId > 0) {
            $checkedSet[$parentCategoryId] = true;
        }

        return $checkedSet;
    }
}
