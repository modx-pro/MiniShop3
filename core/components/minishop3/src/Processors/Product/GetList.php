<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msCategory;
use MiniShop3\Model\msProduct;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOQuery;
use xPDO\Om\xPDOQueryCondition;

/**
 * Combo processor for product search/selection (always called with combo: true).
 *
 * The ExtJS consumer (ms3.combo.Product) is gone; kept for third-party callers
 * that still invoke this processor by action name through the connector.
 */
class GetList extends GetListProcessor
{
    public $classKey = msProduct::class;
    public $languageTopics = ['default', 'minishop3:product'];
    public $defaultSortField = 'menuindex';
    public $defaultSortDirection = 'ASC';

    protected $item_id = 0;

    /**
     * @return bool
     */
    public function initialize()
    {
        if (!$this->getProperty('combo')) {
            $this->addFieldError('combo', $this->modx->lexicon('ms3_err_processor_combo_required'));
            return false;
        }
        if (!$this->getProperty('limit') && $id = (int)$this->getProperty('id')) {
            $this->item_id = $id;
        }
        if (!$this->getProperty('limit')) {
            $this->setProperty('limit', 20);
        }
        if ($this->getProperty('sort') === 'menuindex') {
            $this->setProperty('sort', 'msProduct.parent ' . $this->getProperty('dir') . ', msProduct.menuindex');
        }
        return parent::initialize();
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c->where(['class_key' => 'MiniShop3\Model\msProduct']);
        $c->leftJoin(msProductData::class, 'Data', 'msProduct.id = Data.id');
        $c->leftJoin(msVendor::class, 'Vendor', 'Data.vendor_id = Vendor.id');
        $c->leftJoin(msCategory::class, 'Category', 'Category.id = msProduct.parent');
        $c->select('msProduct.id,msProduct.pagetitle,msProduct.context_key');

        if ($this->item_id) {
            $c->where(['msProduct.id' => $this->item_id]);
        } else {
            $query = trim($this->getProperty('query'));
            if (!empty($query)) {
                if (is_numeric($query)) {
                    $c->where([
                        'msProduct.id' => $query,
                        'OR:Data.article:=' => $query,
                    ]);
                } else {
                    $c->where([
                        'msProduct.pagetitle:LIKE' => "%{$query}%",
                        'OR:msProduct.longtitle:LIKE' => "%{$query}%",
                        'OR:msProduct.description:LIKE' => "%{$query}%",
                        'OR:msProduct.introtext:LIKE' => "%{$query}%",
                        'OR:Data.article:LIKE' => "%{$query}%",
                        'OR:Data.made_in:LIKE' => "%{$query}%",
                        'OR:Vendor.name:LIKE' => "%{$query}%",
                        'OR:Category.pagetitle:LIKE' => "%{$query}%",
                    ]);
                }
            }
        }

        $c->groupby('msProduct.id');

        return $c;
    }

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryAfterCount(xPDOQuery $c)
    {
        $total = 0;
        $limit = (int)$this->getProperty('limit');
        $start = (int)$this->getProperty('start');

        $q = clone $c;
        $q->query['columns'] = ['SQL_CALC_FOUND_ROWS msProduct.id'];
        $sortClassKey = $this->getSortClassKey();
        $sortAlias = $this->getSortClassKey();
        if (strpos($sortAlias, '\\') !== false) {
            $explodedAlias = explode('\\', $sortAlias);
            $sortAlias = array_pop($explodedAlias);
        }
        $sortKey = $this->modx->getSelectColumns(
            $sortClassKey,
            $this->getProperty('sortAlias', $sortAlias),
            '',
            [$this->getProperty('sort')]
        );

        if (empty($sortKey)) {
            $sortKey = $this->getProperty('sort');
        }
        $q->sortby($sortKey, $this->getProperty('dir'));
        if ($limit > 0) {
            $q->limit($limit, $start);
        }

        $ids = [];
        if ($q->prepare() and $q->stmt->execute()) {
            $ids = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
            $total = $this->modx->query('SELECT FOUND_ROWS()')->fetchColumn();
        }
        $ids = empty($ids) ? "(0)" : "(" . implode(',', $ids) . ")";
        $c->query['where'] = [
            [
                new xPDOQueryCondition(['sql' => 'msProduct.id IN ' . $ids, 'conjunction' => 'AND']),
            ]
        ];
        $c->sortby($sortKey, $this->getProperty('dir'));

        $this->setProperty('total', $total);

        return $c;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $c = $this->modx->newQuery($this->classKey);
        $c = $this->prepareQueryBeforeCount($c);
        $c = $this->prepareQueryAfterCount($c);
        $results = ($c->prepare() and $c->stmt->execute()) ? $c->stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        return [
            'results' => $results,
            'total' => (int)$this->getProperty('total'),
        ];
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function iterate(array $data)
    {
        $list = [];
        $list = $this->beforeIteration($list);
        $this->currentIndex = 0;
        foreach ($data['results'] as $array) {
            $list[] = $this->prepareArray($array);
            $this->currentIndex++;
        }
        return $this->afterIteration($list);
    }

    /**
     * @param array $array
     *
     * @return array
     */
    public function prepareArray(array $array)
    {
        $array['parents'] = [];
        $parents = $this->modx->getParentIds($array['id'], 2, [
            'context' => $array['context_key'],
        ]);
        if (empty($parents[count($parents) - 1])) {
            unset($parents[count($parents) - 1]);
        }
        if (!empty($parents) && is_array($parents)) {
            $q = $this->modx->newQuery(msCategory::class, ['id:IN' => $parents]);
            $q->select('id,pagetitle');
            if ($q->prepare() && $q->stmt->execute()) {
                while ($row = $q->stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $key = array_search($row['id'], $parents);
                    if ($key !== false) {
                        $parents[$key] = $row;
                    }
                }
            }
            $array['parents'] = array_reverse($parents);
        }

        return $array;
    }
}
