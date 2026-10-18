<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Services\Customer\CustomerManagerFields;
use MiniShop3\Utils\IntArrayDecoder;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetListCombobox extends GetListProcessor
{
    public $classKey = msCustomer::class;
    public $languageTopics = ['customer'];
    public $defaultSortField = 'id';

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        // id приезжает из запроса и уходит в SQL без подготовки выражения,
        // поэтому приводим к списку положительных целых (#882).
        $ids = IntArrayDecoder::decode($this->getProperty('id'));
        if ($ids !== [] && $this->getProperty('combo')) {
            $c->sortby('FIELD (msCustomer.id, ' . implode(',', $ids) . ')', 'DESC');
        }

        $query = $this->getProperty('query', '');
        if (!empty($query)) {
            $c->where([
                'msCustomer.first_name:LIKE' => "%{$query}%",
                'OR:msCustomer.last_name:LIKE' => "%{$query}%",
                'OR:msCustomer.phone:LIKE' => "%{$query}%",
                'OR:msCustomer.email:LIKE' => "%{$query}%",
            ]);
        }

        return $c;
    }


    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryAfterCount(xPDOQuery $c)
    {
        // Хеш пароля и токен сессии не читаем вовсе: их нет ни в объекте, ни в ответе (#908).
        $c->select($this->modx->getSelectColumns(
            msCustomer::class,
            'msCustomer',
            '',
            CustomerManagerFields::excludedColumns($this->modx->getFieldMeta(msCustomer::class)),
            true
        ));
        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $array = $object->toArray();

        if ($this->getProperty('combo')) {
            $fullname = implode(' ', [$array['first_name'], $array['last_name']]);

            return [
                'id' => $array['id'],
                'fullname' => $fullname,
            ];
        }

        // Без combo строка уходила целиком, вместе с хешем пароля и токеном сессии (#908).
        // Процессор не объявляет своего права, поэтому набор полей ограничиваем всегда.
        return CustomerManagerFields::pick($array);
    }
}
