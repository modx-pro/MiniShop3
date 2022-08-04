<?php

namespace MiniShop3\Processors\Product;

use MODX\Revolution\Processors\Resource\Delete as DeleteResource;

class Delete extends DeleteResource
{
    public $permission = 'msproduct_delete';

    /**
     * @return bool
     */
    public function checkPermissions()
    {
        return empty($this->permission) || $this->modx->hasPermission($this->permission);
    }
}
