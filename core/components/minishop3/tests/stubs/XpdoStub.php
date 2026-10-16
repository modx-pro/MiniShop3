<?php

declare(strict_types=1);

namespace xPDO\Om;

/**
 * Minimal xPDO object base for smoke tests without a live MODX install.
 */
class xPDOSimpleObject
{
    public $xpdo;

    public function __construct(?\xPDO\xPDO $xpdo = null)
    {
        $this->xpdo = $xpdo;
    }

    public function get($key, $format = null, $formatTemplate = null)
    {
        return null;
    }

    public function set($key, $value = null)
    {
        return $this;
    }
}

namespace xPDO;

class xPDO
{
    public $services;

    public function __construct()
    {
        $this->services = new class {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): mixed
            {
                return null;
            }
        };
    }
}
