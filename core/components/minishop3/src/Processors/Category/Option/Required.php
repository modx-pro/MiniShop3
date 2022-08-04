<?php

namespace MiniShop3\Processors\Category\Option;

class Required extends Update
{
    /**
     * @return bool
     */
    public function beforeSet()
    {
        $this->properties = [
            'required' => true,
        ];

        return true;
    }
}
