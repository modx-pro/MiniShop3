<?php

namespace MiniShop3\Processors\Settings\Status;

use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\Processors\Model\GetProcessor;

class Get extends GetProcessor
{
    public $object;
    public $classKey = msOrderStatus::class;
    public $languageTopics = ['minishop3:default', 'minishop3:manager'];
    public $permission = 'mssetting_view';


    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        if (!$this->modx->hasPermission($this->permission)) {
            return $this->modx->lexicon('access_denied');
        }

        return parent::initialize();
    }

    /**
     * @return array
     */
    public function cleanup()
    {
        $data = $this->object->toArray();

        if (!empty($data['name']) && str_starts_with($data['name'], 'ms3_order_status_')) {
            $translated = $this->modx->lexicon($data['name']);
            if ($translated !== $data['name']) {
                $data['name'] = $translated;
            }
        }

        return $this->success('', $data);
    }
}
