<?php

namespace MiniShop3\Processors\System\User;

use MiniShop3\Utils\IntArrayDecoder;
use MODX\Revolution\modUser;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\Processors\Model\GetListProcessor;
use xPDO\Om\xPDOObject;
use xPDO\Om\xPDOQuery;

class GetList extends GetListProcessor
{
    public $classKey = modUser::class;
    public $languageTopics = ['user'];
    public $defaultSortField = 'username';

    /**
     * @param xPDOQuery $c
     *
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $c->leftJoin(modUserProfile::class, 'Profile');

        // id приезжает из запроса и уходит в SQL без подготовки выражения,
        // поэтому приводим к списку положительных целых (#882).
        $ids = IntArrayDecoder::decode($this->getProperty('id'));
        if ($ids !== [] && $this->getProperty('combo')) {
            $c->sortby('FIELD (modUser.id, ' . implode(',', $ids) . ')', 'DESC');
        }

        $query = $this->getProperty('query', '');
        if (!empty($query)) {
            $c->where([
                'modUser.username:LIKE' => "%{$query}%",
                'OR:Profile.fullname:LIKE' => "%{$query}%",
                'OR:Profile.email:LIKE' => "%{$query}%",
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
        $c->select($this->modx->getSelectColumns(modUser::class, 'modUser'));
        $c->select($this->modx->getSelectColumns(modUserProfile::class, 'Profile', '', ['fullname', 'email']));

        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $array = $object->toArray();

        if ($this->getProperty('combo')) {
            $array = [
                'id' => $array['id'],
                'username' => $array['username'],
                'fullname' => !empty($array['fullname']) ? $array['fullname'] : $array['username'],
            ];
        }

        return $array;
    }
}
