<?php

namespace MiniShop3\Processors\Settings\Payment;

class Enable extends Update
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
