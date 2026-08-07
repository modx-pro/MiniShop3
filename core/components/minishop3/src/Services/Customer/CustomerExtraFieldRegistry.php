<?php

declare(strict_types=1);

namespace MiniShop3\Services\Customer;

use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msExtraField;
use MODX\Revolution\modX;

/**
 * DB lookup of active msExtraField keys for msCustomer (#424).
 *
 * Extracted from CustomerPublicDto so the extra-field query is reusable and
 * testable without dragging the whole DTO allowlist along.
 */
final class CustomerExtraFieldRegistry
{
    /**
     * Active msExtraField keys registered for msCustomer.
     *
     * @return list<string>
     */
    public static function activeKeys(modX $modx): array
    {
        $keys = [];
        $iterator = $modx->getIterator(msExtraField::class, [
            'class' => msCustomer::class,
            'active' => 1,
        ]);

        foreach ($iterator as $field) {
            $keys[] = (string) $field->get('key');
        }

        return $keys;
    }
}
