<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Model\msCustomer;
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
        $id = $this->getProperty('id');
        if (!empty($id) && $this->getProperty('combo')) {
            $c->sortby("FIELD (msCustomer.id, {$id})", "DESC");
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
        $c->select($this->modx->getSelectColumns(msCustomer::class, 'msCustomer'));
        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $array = $object->toArray();

        if ($this->getProperty('combo')) {
            //TODO вынести в системные настройки список объединямых полей,для формирование настраеваемого именования
            $fullname = implode(' ', [$array['first_name'], $array['last_name']]);
            $array = [
                'id' => $array['id'],
                'fullname' => $fullname,
            ];
        }

        return $array;
    }
}
