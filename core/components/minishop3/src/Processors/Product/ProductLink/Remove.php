<?php

namespace MiniShop3\Processors\Product\ProductLink;

use MiniShop3\Model\msLink;
use MiniShop3\Model\msProductLink;
use MODX\Revolution\Processors\Model\RemoveProcessor;
use xPDO\Om\xPDOQuery;

class Remove extends RemoveProcessor
{
    public $checkRemovePermission = true;
    public $classKey = msProductLink::class;
    public $languageTopics = ['minishop'];
    public $permission = 'msproduct_save';

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return true;
    }


    /**
     * @return array|string
     */
    public function process()
    {
        $canRemove = $this->beforeRemove();
        if ($canRemove !== true) {
            return $this->failure($canRemove);
        }

        $link = $this->getProperty('link');
        $master = $this->getProperty('master');
        $slave = $this->getProperty('slave');

        if (!$link || !$master || !$slave) {
            return $this->failure('Wrong object key');
        }

        /** @var msLink $msLink */
        $msLink = $this->modx->getObject(msLink::class, ['id' => $link]);
        if (!$msLink) {
            return $this->failure($this->modx->lexicon('ms_err_no_link'));
        }
        $type = $msLink->get('type');

        $q = $this->modx->newQuery(msProductLink::class);
        $q->command('DELETE');
        $q->where(['link' => $link]);
        switch ($type) {
            case 'many_to_many':
                $q->where(['master' => $slave, 'OR:slave:=' => $slave]);
                break;

            case 'one_to_one':
                $q->where([
                    ['master' => $master, 'AND:slave:=' => $slave],
                    ['master' => $slave, 'AND:slave:=' => $master]
                ], xPDOQuery::SQL_OR);
                break;

            case 'many_to_one':
            case 'one_to_many':
                $q->where(['master' => $master, 'slave' => $slave]);
                break;
        }
        $q->prepare();
        $q->stmt->execute();

        return $this->success('');
    }
}
