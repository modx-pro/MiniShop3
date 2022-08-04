<?php

namespace MiniShop3\Processors\Settings\Delivery;

class Disable extends Update
{
    /**
     * @return bool
     */
    public function beforeSet()
    {
        $this->properties = [
            'active' => false,
        ];

        return true;
    }
}
