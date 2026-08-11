<?php

namespace MiniShop3\Processors\Product\ProductLink;

use MiniShop3\Model\msProductLink;
use MiniShop3\Services\Product\ProductLinkService;
use MODX\Revolution\Processors\Model\RemoveProcessor;

class Remove extends RemoveProcessor
{
    public $checkRemovePermission = true;
    public $classKey = msProductLink::class;
    public $languageTopics = ['minishop3'];
    public $permission = 'msproduct_save';

    /**
     * @return bool|null|string
     */
    public function initialize()
    {
        return true;
    }

    /**
     * @return array|string
     */
    public function process()
    {
        $service = $this->modx->services->has('ms3_product_link_service')
            ? $this->modx->services->get('ms3_product_link_service')
            : new ProductLinkService($this->modx);

        $result = $service->remove(
            (int) $this->getProperty('link'),
            (int) $this->getProperty('master'),
            (int) $this->getProperty('slave')
        );

        if (empty($result['ok'])) {
            return $this->failure($result['message'] ?? '');
        }

        return $this->success('');
    }
}
