<?php

namespace MiniShop3\Processors\Category\Option;

class Deactivate extends Update
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
