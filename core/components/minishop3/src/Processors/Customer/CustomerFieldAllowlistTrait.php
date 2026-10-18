<?php

namespace MiniShop3\Processors\Customer;

use MiniShop3\Services\Customer\CustomerExtraFieldRegistry;
use MiniShop3\Services\Customer\CustomerManagerFields;

/**
 * Manager-side customer payload: the allowlist plus the site's own msExtraField columns (#908).
 *
 * CustomerManagerFields is fail-closed, so a column registered through msExtraField would
 * silently drop out of these processors without this passthrough — the columns themselves
 * live in the xPDO map, which the plugin loads on OnMODXInit, not in the model file.
 *
 * Keys are resolved once per processor run: prepareRow() / prepareArray() run per row.
 *
 * @property \MODX\Revolution\modX $modx
 */
trait CustomerFieldAllowlistTrait
{
    /** @var list<string>|null */
    protected ?array $ms3CustomerExtraFieldKeys = null;

    /**
     * Active msExtraField keys registered for msCustomer.
     *
     * @return list<string>
     */
    protected function extraFieldKeys(): array
    {
        if ($this->ms3CustomerExtraFieldKeys === null) {
            $this->ms3CustomerExtraFieldKeys = CustomerExtraFieldRegistry::activeKeys($this->modx);
        }

        return $this->ms3CustomerExtraFieldKeys;
    }

    /**
     * Customer row narrowed to the fields a manager-side processor may return.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    protected function allowedCustomerFields(array $row): array
    {
        return CustomerManagerFields::pick($row, $this->extraFieldKeys());
    }
}
