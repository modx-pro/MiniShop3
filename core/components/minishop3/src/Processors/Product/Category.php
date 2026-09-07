<?php

namespace MiniShop3\Processors\Product;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Services\Category\CategoryProductMenuindexService;
use MODX\Revolution\Processors\Model\CreateProcessor;

class Category extends CreateProcessor
{
    public $classKey = msCategoryMember::class;
    public $permission = 'msproduct_save';


    /**
     * @return array|string
     */
    public function process()
    {
        $pid = $this->getProperty('product_id');
        $cid = $this->getProperty('category_id');
        if ($pid > 0 && $cid > 0) {
            /** @var msCategoryMember $res */
            $res = $this->modx->getObject(msCategoryMember::class, ['category_id' => $cid, 'product_id' => $pid]);
            if (!$res) {
                $menuindexService = new CategoryProductMenuindexService($this->modx);
                $menuindexService->ensureMember((int) $pid, (int) $cid);
            } else {
                $res->remove();
            }
        }

        return $this->success('');
    }
}
