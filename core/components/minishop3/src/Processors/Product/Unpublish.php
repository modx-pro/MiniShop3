<?php

namespace MiniShop3\Processors\Product;

use MODX\Revolution\Processors\Resource\Unpublish as UnpublishResource;

class Unpublish extends UnpublishResource
{
    public $permission = 'msproduct_publish';

    /**
     * @return bool
     */
    public function checkPermissions()
    {
        return empty($this->permission) || $this->modx->hasPermission($this->permission);
    }
}
