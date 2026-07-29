<?php

namespace MiniShop3\Services\Cart;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Services\Order\OrderDraftManager;
use MiniShop3\Services\Order\OrderLogService;
use MODX\Revolution\modX;

/**
 * Cart Mutation Handler
 *
 * Handles cart mutation pipelines: add, change, changeOption, remove.
 * Manages events, persistence, recalculation, and recursive delegation.
 */
class CartMutationHandler
{
    protected modX $modx;
    protected MiniShop3 $ms3;
    protected OrderDraftManager $draftManager;
    protected CartItemManager $itemManager;
    protected OrderLogService $orderLog;

    public function __construct(
        modX $modx,
        MiniShop3 $ms3,
        OrderDraftManager $draftManager,
        CartItemManager $itemManager,
        OrderLogService $orderLog
    ) {
        $this->modx = $modx;
        $this->ms3 = $ms3;
        $this->draftManager = $draftManager;
        $this->itemManager = $itemManager;
        $this->orderLog = $orderLog;
    }

    /**
     * Add product to cart
     *
     * @param object $controller Cart facade (event payload BC)
     */
    public function add(
        object $controller,
        msOrder $draft,
        array $cart,
        int $id,
        int $count = 1,
        array $options = []
    ): array {
        if ($id <= 0) {
            return $this->ms3->utils->error('ms3_cart_add_err_id');
        }

        $count = (int)$count;
        $options = $this->itemManager->normalizeOptions($options);

        if (!$this->itemManager->validateCount($count)) {
            return $this->errorWithStatus(
                $controller,
                $cart,
                'ms3_cart_add_err_count',
                ['count' => $count]
            );
        }

        $product = $this->itemManager->validateProduct($id);
        if (!$product) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_add_err_nf');
        }

        $response = $this->invokeEvent($controller, 'msOnBeforeAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }

        $count = $response['data']['count'];
        $options = $response['data']['options'];

        $productKey = $this->itemManager->generateProductKey($product->toArray(), $options);

        if (isset($cart[$productKey])) {
            return $this->change($controller, $draft, $cart, $productKey, $cart[$productKey]['count'] + $count);
        }

        $cartItem = $this->itemManager->addItem($draft, $product, $count, $options, $productKey);

        $this->orderLog->addEntry(
            $draft->get('id'),
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'add',
                'product_id' => $id,
                'product_name' => $product->get('pagetitle'),
                'count' => $count,
                'price' => $cartItem->get('price'),
                'cost' => $cartItem->get('cost'),
            ]
        );

        $this->draftManager->recalculate($draft);
        $cart = $this->reloadCart($draft);

        $response = $this->invokeEvent($controller, 'msOnAddToCart', [
            'msProduct' => $product,
            'count' => $count,
            'options' => $options,
            'product_key' => $productKey,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }

        return $this->successWithCart($controller, $draft, $cart, 'ms3_cart_add_success', [
            'last_key' => $productKey,
        ], ['count' => $count]);
    }

    /**
     * Change product quantity in cart
     *
     * @param object $controller Cart facade (event payload BC)
     */
    public function change(
        object $controller,
        msOrder $draft,
        array $cart,
        string $productKey,
        int $count
    ): array {
        if (!isset($cart[$productKey])) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_change_error');
        }

        $count = (int)$count;

        if ($count <= 0) {
            return $this->remove($controller, $draft, $cart, $productKey);
        }

        if (!$this->itemManager->validateCount($count)) {
            return $this->errorWithStatus(
                $controller,
                $cart,
                'ms3_cart_add_err_count',
                ['count' => $count]
            );
        }

        $response = $this->invokeEvent($controller, 'msOnBeforeChangeInCart', [
            'product_key' => $productKey,
            'count' => $count,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }
        $count = $response['data']['count'];

        $oldCount = $cart[$productKey]['count'];
        $productId = $cart[$productKey]['product_id'];
        $productName = $cart[$productKey]['name'] ?? '';

        $this->itemManager->updateItemCount($draft, $productKey, $count);

        if ($oldCount != $count) {
            $this->orderLog->addEntry(
                $draft->get('id'),
                msOrderLog::ACTION_PRODUCTS,
                [
                    'operation' => 'update',
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'changes' => [
                        'count' => ['old' => $oldCount, 'new' => $count],
                    ],
                ]
            );
        }

        $this->draftManager->recalculate($draft);
        $cart = $this->reloadCart($draft);

        $this->invokeEvent($controller, 'msOnChangeInCart', [
            'product_key' => $productKey,
            'count' => $count,
        ]);

        return $this->successWithCart($controller, $draft, $cart, 'ms3_cart_change_success', [
            'last_key' => $productKey,
        ], ['count' => $count]);
    }

    /**
     * Change product options in cart
     *
     * @param object $controller Cart facade (event payload BC)
     */
    public function changeOption(
        object $controller,
        msOrder $draft,
        array $cart,
        string $productKey,
        array $options
    ): array {
        if (!isset($cart[$productKey])) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_change_error');
        }

        if (empty($options)) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_change_options_error');
        }

        $response = $this->invokeEvent($controller, 'msOnBeforeChangeOptionsInCart', [
            'product_key' => $productKey,
            'options' => $options,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }
        if (isset($response['data']['options']) && is_array($response['data']['options'])) {
            $options = $response['data']['options'];
        }

        $count = $cart[$productKey]['count'];

        $item = $this->itemManager->getItemByKey($draft, $productKey);
        if ($item) {
            $currentOptions = $item->get('options') ?? [];
            foreach ($options as $key => $value) {
                if (!empty($value)) {
                    $currentOptions[$key] = $value;
                } else {
                    unset($currentOptions[$key]);
                }
            }

            $product = $item->getOne('Product');
            if ($product) {
                $newProductKey = $this->itemManager->generateProductKey($product->toArray(), $currentOptions);

                if ($newProductKey !== $productKey && isset($cart[$newProductKey])) {
                    $item->remove();
                    return $this->change(
                        $controller,
                        $draft,
                        $this->reloadCart($draft),
                        $newProductKey,
                        $cart[$newProductKey]['count'] + $count
                    );
                }
            }
        }

        $newProductKey = $this->itemManager->updateItemOptions($draft, $productKey, $options);

        if (!$newProductKey) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_change_error');
        }

        $this->draftManager->recalculate($draft);
        $cart = $this->reloadCart($draft);

        $this->invokeEvent($controller, 'msOnChangeOptionInCart', [
            'old_product_key' => $productKey,
            'product_key' => $newProductKey,
            'options' => $options,
        ]);

        return $this->successWithCart($controller, $draft, $cart, 'ms3_cart_change_options_success', [
            'last_key' => $newProductKey,
        ], ['count' => $count]);
    }

    /**
     * Remove product from cart
     *
     * @param object $controller Cart facade (event payload BC)
     */
    public function remove(
        object $controller,
        msOrder $draft,
        array $cart,
        string $productKey
    ): array {
        if (!isset($cart[$productKey])) {
            return $this->errorWithStatus($controller, $cart, 'ms3_cart_change_error');
        }

        $response = $this->invokeEvent($controller, 'msOnBeforeRemoveFromCart', [
            'product_key' => $productKey,
        ]);
        if (!$response['success']) {
            return $this->ms3->utils->error($response['message']);
        }

        $itemData = $this->itemManager->getItemDataForLog($draft, $productKey);
        $orderId = $draft->get('id');

        $this->itemManager->removeItem($draft, $productKey);

        $this->orderLog->addEntry(
            $orderId,
            msOrderLog::ACTION_PRODUCTS,
            [
                'operation' => 'remove',
                'product_id' => $itemData['product_id'] ?? 0,
                'product_name' => $itemData['product_name'] ?? '',
                'count' => $itemData['count'] ?? 0,
                'price' => $itemData['price'] ?? 0,
            ]
        );

        if ($this->draftManager->isEmpty($draft)) {
            $this->draftManager->deleteDraft($draft);
            $draft = null;
            $cart = [];
        } else {
            $this->draftManager->recalculate($draft);
            $cart = $this->reloadCart($draft);
        }

        $this->invokeEvent($controller, 'msOnRemoveFromCart', [
            'product_key' => $productKey,
        ]);

        return $this->successWithCart($controller, $draft, $cart, 'ms3_cart_remove_success', [
            'last_key' => $productKey,
        ]);
    }

    /**
     * @param object $controller Cart facade (event payload BC)
     */
    protected function successWithCart(
        object $controller,
        ?msOrder $draft,
        array $cart,
        string $message,
        array $data = [],
        array $placeholders = []
    ): array {
        return $this->ms3->utils->success($message, array_merge($data, [
            'cart' => $cart,
            'draft' => $draft,
            'status' => $this->buildStatus($controller, $cart),
        ]), $placeholders);
    }

    /**
     * @param object $controller Cart facade (event payload BC)
     */
    protected function errorWithStatus(
        object $controller,
        array $cart,
        string $message,
        array $placeholders = []
    ): array {
        return $this->ms3->utils->error(
            $message,
            $this->buildStatus($controller, $cart),
            $placeholders
        );
    }

    /**
     * @param object $controller Cart facade (event payload BC)
     */
    protected function buildStatus(object $controller, array $cart): array
    {
        $status = $this->itemManager->calculateStatus($cart);

        $response = $this->invokeEvent($controller, 'msOnGetStatusCart', [
            'status' => $status,
        ]);

        if ($response['success'] && isset($response['data']['status'])) {
            return $response['data']['status'];
        }

        return $status;
    }

    protected function reloadCart(?msOrder $draft): array
    {
        if (!$draft) {
            return [];
        }

        return $this->itemManager->loadItems($draft);
    }

    /**
     * @param object $controller Cart facade (event payload BC)
     */
    protected function invokeEvent(object $controller, string $eventName, array $params = []): array
    {
        $params['controller'] = $controller;

        return $this->ms3->utils->invokeEvent($eventName, $params);
    }
}
