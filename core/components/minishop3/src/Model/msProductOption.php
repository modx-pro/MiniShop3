<?php

namespace MiniShop3\Model;

use MODX\Revolution\modCategory;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;
use xPDO\xPDO;

/**
 * Class msProductOption
 *
 * @property integer $product_id
 * @property string $key
 * @property string $value
 *
 * @package MiniShop3\Model
 */
class msProductOption extends xPDOObject
{
    /**
     * @param xPDO $xpdo
     * @param int $product_id
     *
     * @return array
     */
    public static function loadOptions(xPDO $xpdo, $product_id)
    {
        $c = $xpdo->newQuery(msProductOption::class);
        $c->rightJoin(msOption::class, 'msOption', 'msProductOption.key=msOption.key');
        $c->leftJoin(modCategory::class, 'Category', 'Category.id=msOption.category_id');
        $c->where(['msProductOption.product_id' => $product_id]);
        $c->select($xpdo->getSelectColumns(msOption::class, 'msOption'));
        $c->select($xpdo->getSelectColumns(msProductOption::class, 'msProductOption', '', ['key'], true));
        $c->select('Category.category AS category_name');
        $data = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($option = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                // If the option is repeated, its value will be an array
                if (isset($data[$option['key']])) {
                    $data[$option['key']][] = $option['value'];
                } else {
                    $data[$option['key']] = [$option['value']];
                }
                foreach ($option as $key => $value) {
                    $data[$option['key'] . '.' . $key] = $value;
                }
            }
        }

        return $data;
    }

    /**
     *
     */
    public function saveProductOptions($product_id, $options, $removeOther = true)
    {
        $existingOptions = $reducedArray = $this->getForProduct($product_id);
        $table = $this->xpdo->getTableName(msProductOption::class);
        $add = $this->xpdo->prepare("INSERT INTO {$table} (`product_id`, `key`, `value`) VALUES ({$product_id}, ?, ?)");
        $update = $this->xpdo->prepare(
            "UPDATE {$table} SET `value` = ?  WHERE `product_id` = {$product_id}  AND `key` = ?"
        );
        $remove = $this->xpdo->prepare("DELETE FROM {$table}  WHERE `product_id` = {$product_id} AND `key` = ?");
        if (is_array($options)) {
            foreach ($options as $key => $array) {
                $array = $this->prepareOptionValues($array);

                if (is_array($array)) {
                    foreach ($array as $k => $value) {
                        if (count($array) > 1) {
                            if (empty($existingOptions[$key]) || !in_array($value, $existingOptions[$key])) {
                                $add->execute([$key, $value]);
                                continue;
                            }
                        } else {
                            if (empty($existingOptions[$key])) {
                                $add->execute([$key, $value]);
                                continue;
                            }

                            if (count($existingOptions[$key]) === 1 && $existingOptions[$key][0] !== $value) {
                                $update->execute([$value, $key]);
                                unset($reducedArray[$key]);
                                continue;
                            }

                            if (count($existingOptions[$key]) > 1) {
                                foreach ($existingOptions[$key] as $i => $v) {
                                    if ($v === $value) {
                                        unset($reducedArray[$key][$i]);
                                    }
                                }
                                continue;
                            }
                        }

                        if (!is_array($existingOptions[$key])) {
                            unset($reducedArray[$key]);
                            continue;
                        }
                        if (in_array($value, $existingOptions[$key])) {
                            unset($reducedArray[$key][$k]);
                        }
                        if (empty($reducedArray[$key])) {
                            unset($reducedArray[$key]);
                        }
                    }
                }
            }
        }

        if (!empty($reducedArray) && $removeOther) {
            foreach ($reducedArray as $key => $value) {
                $remove->execute([$key]);
            }
        }
    }

    /**
     * @return array
     */
    public function getOptionFields($product_id)
    {
        $fields = [];
        /** @var xPDOQuery $c */
        $c = $this->prepareOptionListCriteria($product_id);

        $c->select([
            $this->xpdo->getSelectColumns(msOption::class, 'msOption'),
            $this->xpdo->getSelectColumns(
                msCategoryOption::class,
                'msCategoryOption',
                '',
                ['id', 'option_id', 'category_id'],
                true
            ),
            'Category.category AS category_name',
        ]);

        $options = $this->xpdo->getIterator(msOption::class, $c);

        /** @var msOption $option */
        foreach ($options as $option) {
            $field = $option->toArray();
            $value = $option->getValue($product_id);
            $field['value'] = !is_null($value) ? $value : $field['value'];
            $field['ext_field'] = $option->getManagerField($field);
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @param bool $force
     *
     * @return array
     */
    public function getOptionKeys($product_id)
    {
        /** @var xPDOQuery $c */
        $c = $this->prepareOptionListCriteria($product_id);

        $c->groupby('msOption.id');
        $c->select('msOption.key');

        return $c->prepare() && $c->stmt->execute()
            ? $c->stmt->fetchAll(\PDO::FETCH_COLUMN)
            : [];
    }

    /**
     * @param int $product_id
     * @return array
     */
    public function getForProduct($product_id)
    {
        $c = $this->xpdo->newQuery(msProductOption::class, ['product_id' => $product_id]);
        $c->select('key,value');
        $c->sortby('value');
        $value = [];
        if ($c->prepare() && $c->stmt->execute()) {
            while ($row = $c->stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (isset($value[$row['key']])) {
                    $value[$row['key']][] = $row['value'];
                } else {
                    $value[$row['key']] = [$row['value']];
                }
            }
        }
        return $value;
    }

    /**
     * @return \xPDO\Om\xPDOQuery
     */
    private function prepareOptionListCriteria($product_id)
    {
        $categories = [];
        $q = $this->xpdo->newQuery(msCategoryMember::class, ['product_id' => $product_id]);
        $q->select('category_id');
        if ($q->prepare() && $q->stmt->execute()) {
            $categories = $q->stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        if ($product = $this->getOne('Product')) {
            $categories[] = $product->get('parent');
        } elseif (!empty($_GET['parent'])) {
            $categories[] = (int)$_GET['parent'];
        }
        $categories = array_unique($categories);

        $c = $this->xpdo->newQuery(msOption::class);
        $c->leftJoin(msCategoryOption::class, 'msCategoryOption', 'msCategoryOption.option_id = msOption.id');
        $c->leftJoin(modCategory::class, 'Category', 'Category.id = msOption.category_id');
        $c->sortby('msCategoryOption.position');
        $c->where(['msCategoryOption.active' => 1]);
        if (!empty($categories[0])) {
            $c->where(['msCategoryOption.category_id:IN' => $categories]);
        }
        $c->groupby('msOption.id');

        return $c;
    }

    /**
     * @param null $values
     *
     * @return array|null
     */
    private function prepareOptionValues($values = null)
    {
        if ($values !== null) {
            if (!is_array($values)) {
                $values = [$values];
            }
            // fix duplicate, empty option values
            $values = array_map('trim', $values);
            $values = array_keys(array_flip($values));
            $values = array_diff($values, ['']);
            if (empty($values)) {
                $values = null;
            }
        }

        return $values;
    }
}
