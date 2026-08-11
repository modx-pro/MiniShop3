<?php

namespace MiniShop3\Processors\Product\ProductLink;

use MiniShop3\Model\msProductLink;
use MiniShop3\Services\Product\ProductLinkService;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Create extends CreateProcessor
{
    public $classKey = msProductLink::class;
    public $languageTopics = ['minishop3:default'];
    public $permission = 'msproduct_save';

    /**
     * @return array|string
     */
    public function process()
    {
        $service = $this->modx->services->has('ms3_product_link_service')
            ? $this->modx->services->get('ms3_product_link_service')
            : new ProductLinkService($this->modx);

        $result = $service->create(
            (int) $this->getProperty('master'),
            (int) $this->getProperty('slave'),
            (int) $this->getProperty('link')
        );

        if (empty($result['ok'])) {
            return $this->failure($result['message'] ?? '');
        }

        return $this->success('');
    }
}
