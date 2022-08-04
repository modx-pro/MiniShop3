<?php

namespace MiniShop3\Processors\Product;

use MODX\Revolution\Processors\Resource\Undelete as UndeleteResource;

class Undelete extends UndeleteResource
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
