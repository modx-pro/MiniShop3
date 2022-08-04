<?php

namespace MiniShop3\Processors\Category\Option;

class Unrequired extends Update
{
    /**
     * @return bool
     */
    public function beforeSet()
    {
        $this->properties = [
            'required' => false,
        ];

        return true;
    }
}
