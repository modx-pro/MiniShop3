<?php

declare(strict_types=1);

namespace xPDO\Om;

use xPDO\xPDO;

class xPDOObject
{
}

class xPDOSimpleObject extends xPDOObject
{
    /** @var xPDO|null */
    protected $xpdo;

    public function __construct(?xPDO $xpdo = null)
    {
        $this->xpdo = $xpdo;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return null;
    }
}
