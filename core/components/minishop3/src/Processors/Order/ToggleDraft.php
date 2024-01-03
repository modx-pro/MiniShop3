<?php

namespace MiniShop3\Processors\Order;

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\Processors\ModelProcessor;

class ToggleDraft extends ModelProcessor
{
    /**
     * @return array|string
     */
    public function process()
    {

        $item = $this->modx->getObject(modSystemSetting::class, [
            'key' => 'ms3_order_show_drafts'
        ]);
        $item->set('value', !$item->get('value'));
        $item->save();
        return $this->success();
    }
}
