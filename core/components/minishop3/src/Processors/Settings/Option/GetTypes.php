<?php

namespace MiniShop3\Processors\Settings\Option;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msOption;
use MODX\Revolution\modCategory;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetTypes extends GetListProcessor
{
    public $languageTopics = ['minishop3:manager'];


    /**
     * Get the data of the query
     * @return array
     */
    public function getData()
    {
        /** @var MiniShop3 $ms3 */
        $ms3 = $this->modx->services->get('ms3');
        $data = array();
        $limit = intval($this->getProperty('limit'));
        $start = intval($this->getProperty('start'));

        $files = $ms3->options->loadOptionTypeList();
        $data['results'] = [];
        foreach ($files as $name) {
            $className = $ms3->options->loadOptionType($name);
            if (class_exists($className)) {
                $name = lcfirst($name);
                $data['results'][] = [
                    'name' => $name,
                    'caption' => $this->modx->lexicon('ms3_ft_' . $name),
                    'xtype' => $className::$xtype,
                ];
            }
        }

        $data['total'] = count($data['results']);
        if ($limit > 0) {
            $data['results'] = array_slice($data['results'], $start, $limit);
        }

        return $data;
    }


    /**
     * Iterate across the data
     *
     * @param array $data
     *
     * @return array
     */
    public function iterate(array $data)
    {
        $list = [];
        $list = $this->beforeIteration($list);
        $this->currentIndex = 0;
        /** @var array $array */
        foreach ($data['results'] as $array) {
            $array = $this->prepareArray($array);
            if (!empty($array) && is_array($array)) {
                $list[] = $array;
                $this->currentIndex++;
            }
        }
        return $this->afterIteration($list);
    }


    /**
     * Prepare the row for iteration
     *
     * @param array $array
     *
     * @return array
     */
    public function prepareArray($array)
    {
        return $array;
    }
}
