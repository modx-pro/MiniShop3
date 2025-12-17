<?php

namespace MiniShop3\Model;

use xPDO\Om\xPDOSimpleObject;

/**
 * Class msOrderLog
 *
 * @property integer $user_id
 * @property integer $order_id
 * @property string $timestamp
 * @property string $action
 * @property array $entry
 * @property boolean $visible
 * @property array $ip
 *
 * @package MiniShop3\Model
 */
class msOrderLog extends xPDOSimpleObject
{
    /** Action: Order status changed */
    public const ACTION_STATUS = 'status';

    /** Action: Payment or refund operation */
    public const ACTION_PAYMENT = 'payment';

    /** Action: Order products changed (add/update/remove) */
    public const ACTION_PRODUCTS = 'products';

    /** Action: Order address changed */
    public const ACTION_ADDRESS = 'address';

    /** Action: Order field changed */
    public const ACTION_FIELD = 'field';

    /** All available action types */
    public const ALL_ACTIONS = [
        self::ACTION_STATUS,
        self::ACTION_PAYMENT,
        self::ACTION_PRODUCTS,
        self::ACTION_ADDRESS,
        self::ACTION_FIELD,
    ];

    /**
     * Get entry data as array (decoded JSON)
     *
     * For backward compatibility with old string entries,
     * returns ['value' => $entry] if entry is not valid JSON
     *
     * @return array
     */
    public function getEntryData(): array
    {
        $entry = $this->get('entry');

        if (is_array($entry)) {
            return $entry;
        }

        if (is_string($entry)) {
            $decoded = json_decode($entry, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return ['value' => $entry];
        }

        return [];
    }

    /**
     * Set entry data from array (will be JSON encoded on save)
     *
     * @param array $data
     * @return void
     */
    public function setEntryData(array $data): void
    {
        $this->set('entry', $data);
    }
}
