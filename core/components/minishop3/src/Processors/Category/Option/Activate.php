<?php

namespace MiniShop3\Processors\Category\Option;

class Activate extends Update
{
    /**
     * @return bool
     */
    public function beforeSet()
    {
        $this->properties = [
            'active' => true,
        ];

        return true;
    }
}
