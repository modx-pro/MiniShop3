<?php

namespace MiniShop3\Controllers\Order;

interface OrderInterface
{

    /**
     * Initializes order to context
     * Here you can load custom javascript or styles
     *
     * @param string $token
     * @param array $config
     * @return boolean
     */
    public function initialize(string $token = '', array $config = []): bool;

    /**
     * Add one field to order
     *
     * @param string $key Name of the field
     * @param string $value .Value of the field
     *
     * @return boolean
     */
    public function add(string $key, mixed $value): bool;

    /**
     * Validates field before it set
     *
     * @param string $key The key of the field
     * @param string $value .Value of the field
     *
     * @return boolean|mixed
     */
    public function validate(string $key, mixed $value): mixed;

    /**
     * Removes field from order
     *
     * @param string $key The key of the field
     *
     * @return boolean
     */
    public function remove(string $key): bool;

    /**
     * Returns the whole order
     *
     * @return array $order
     */
    public function get(): array;

    /**
     * Returns the one field of order
     *
     * @param array $order Whole order at one time
     *
     * @return array $order
     */
    public function set(array $order): array;

    /**
     * Submit the order. It will create record in database and redirect user to payment, if set.
     *
     * @return array $status Array with order status
     */
    public function submit(): array;

    /**
     * Cleans the order
     *
     * @return boolean
     */
    public function clean(): bool;

    /**
     * Returns the cost of delivery depending on its settings and the goods in a cart
     *
     * @return array $response
     */
    public function getCost(): array;
}
