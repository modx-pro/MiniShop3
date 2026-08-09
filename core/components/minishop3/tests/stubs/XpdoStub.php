<?php

declare(strict_types=1);

namespace xPDO\Om;

/**
 * Minimal xPDO object base for smoke tests without a live MODX install.
 */
class xPDOSimpleObject
{
    public function get(string $key, $format = null, $formatTemplate = null): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value): self
    {
        return $this;
    }
}
