<?php

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Order\ManagerOrderCostRecalculator;
use MiniShop3\Services\Order\ManagerOrderListService;
use MiniShop3\Services\Order\ManagerOrderMutationService;
use MiniShop3\Services\Order\ManagerOrderPresenter;
use MiniShop3\Services\Order\ManagerOrderProductsService;
use MiniShop3\Services\Order\OrderLogService;
use MODX\Revolution\modUser;
use MODX\Revolution\modX;

/**
 * API controller for order management (Manager API)
 *
 * Thin HTTP layer that validates route params and delegates business logic
 * to dedicated manager order services.
 *
 * @package MiniShop3\Controllers\Api\Manager
 */
class OrdersController
{
    protected const DIRECT_FILTER_KEYS = ManagerOrderListService::DIRECT_FILTER_KEYS;
    protected const DIRECT_FILTER_FIELD_MAP = ManagerOrderListService::DIRECT_FILTER_FIELD_MAP;
    protected const HIDDEN_ORDER_FIELDS = ManagerOrderPresenter::HIDDEN_ORDER_FIELDS;

    protected modX $modx;
    protected ?OrderLogService $orderLog = null;
    protected ?ManagerOrderPresenter $presenter = null;
    protected ?ManagerOrderListService $listService = null;
    protected ?ManagerOrderMutationService $mutationService = null;
    protected ?ManagerOrderProductsService $productsService = null;
    protected ?ManagerOrderCostRecalculator $costRecalculator = null;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->loadExtraFieldsMap();
    }

    public static function getDirectFilterKeys(): array
    {
        return self::DIRECT_FILTER_KEYS;
    }

    /**
     * Load extra fields into xPDO map.
     * This ensures dynamic columns added via Object Extension are available.
     */
    protected function loadExtraFieldsMap(): void
    {
        $ms3 = $this->modx->services->get('ms3');
        if ($ms3) {
            $ms3->loadMap();
        }
    }

    public function getList(array $params = []): array
    {
        return Response::success($this->getListService()->getList($params))->getData();
    }

    /**
     * Aggregated order stats for the manager grid (same filters as getList, no pagination).
     * GET /api/mgr/orders/stats
     *
     * @param array $params Filter parameters (filter_*, show_drafts, …)
     * @return array Response envelope data
     */
    public function getStats(array $params = []): array
    {
        return Response::success($this->getListService()->getOrdersStats($params))->getData();
    }

    public function get(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Order ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $id);
        if (!$order) {
            return Response::error('Order not found', HttpStatus::NOT_FOUND)->getData();
        }

        return Response::success($this->getPresenter()->buildOrderPayloadFromModel($order))->getData();
    }

    /**
     * Пересчитать стоимость заказа по сохранённым позициям и текущим delivery_id/payment_id.
     *
     * POST /api/mgr/orders/{id}/recalculate-cost
     * Body JSON: mode (auto|manual|force_provider), optional manual_delivery_cost
     *
     * Ответ дополняет поля заказа (как GET) ключами breakdown и warnings из пересчёта.
     */
    public function recalculateCost(array $params = []): array
    {
        $this->modx->lexicon->load('minishop3:default');

        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Order ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $id);
        if (!$order) {
            return Response::error('Order not found', HttpStatus::NOT_FOUND)->getData();
        }

        $modeIn = strtolower(trim((string)($params['mode'] ?? ManagerOrderCostRecalculator::MODE_AUTO)));
        $allowedModes = [
            ManagerOrderCostRecalculator::MODE_AUTO,
            ManagerOrderCostRecalculator::MODE_MANUAL,
            ManagerOrderCostRecalculator::MODE_FORCE_PROVIDER,
        ];
        if (!in_array($modeIn, $allowedModes, true)) {
            $modeIn = ManagerOrderCostRecalculator::MODE_AUTO;
        }

        $options = ['mode' => $modeIn];
        if (array_key_exists('manual_delivery_cost', $params)) {
            $options['manual_delivery_cost'] = $params['manual_delivery_cost'];
        }

        $result = $this->getOrderCostRecalculator()->recalculate($order, $options);
        if (empty($result['success'])) {
            return Response::error(
                $this->getPresenter()->lexiconMessageOrKey((string)($result['message'] ?? 'ms3_err_unknown')),
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $serviceData = is_array($result['data']) ? $result['data'] : [];

        $fresh = $this->modx->getObject(msOrder::class, $id);
        if (!$fresh instanceof msOrder) {
            return Response::error('Order not found after recalculation', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        $payload = $this->getPresenter()->buildOrderPayloadFromModel($fresh);
        if (!empty($serviceData['breakdown'])) {
            $payload['breakdown'] = $serviceData['breakdown'];
        }
        $payload['warnings'] = $serviceData['warnings'] ?? [];

        $messageKey = 'ms3_order_cost_recalc_success';
        $message = $this->getPresenter()->lexiconMessageOrKey($messageKey);
        if ($message === $messageKey) {
            $message = '';
        }

        return Response::success($payload, $message)->getData();
    }

    public function delete(array $params = []): array
    {
        return $this->wrapServiceResult($this->getMutationService()->delete($params));
    }

    public function bulkDelete(array $params = []): array
    {
        return $this->wrapServiceResult($this->getMutationService()->bulkDelete($params));
    }

    public function create(array $params = []): array
    {
        return $this->wrapServiceResult($this->getMutationService()->create($params));
    }

    /**
     * Finalize order (convert draft to final order)
     * POST /api/mgr/orders/{id}/finalize
     */
    public function finalize(array $params = []): array
    {
        $orderId = (int)($params['id'] ?? 0);
        if (!$orderId) {
            return Response::error('Order ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $options = [
            'skip_notifications' => !empty($params['skip_notifications']),
            'create_customer' => !empty($params['create_customer']),
            'force_create_customer' => !empty($params['force_create_customer']),
        ];

        $finalizeService = $this->modx->services->get('ms3_order_finalize');
        $result = $finalizeService->finalize($orderId, $options);

        // Check if duplicate customer was found - return for user decision
        if ($result['success'] && !empty($result['data']['duplicate_found'])) {
            return Response::success($result['data'], 'Customer with matching data already exists')->getData();
        }

        if (!$result['success']) {
            $message = $result['message'] ?? 'ms3_err_unknown';
            $translatedMessage = $this->modx->lexicon($message);
            if ($translatedMessage === $message) {
                $translatedMessage = $message;
            }

            return Response::error($translatedMessage, HttpStatus::BAD_REQUEST, $result['data'] ?? [])->getData();
        }

        $order = $this->modx->getObject(msOrder::class, $orderId);
        if (!$order) {
            return Response::error('Order not found after finalization', HttpStatus::INTERNAL_SERVER_ERROR)->getData();
        }

        return Response::success(
            $this->getPresenter()->buildOrderPayloadFromModel($order),
            'ms3_order_finalized'
        )->getData();
    }

    public function update(array $params = []): array
    {
        return $this->wrapServiceResult($this->getMutationService()->update($params));
    }

    public function getProducts(array $params = []): array
    {
        return $this->wrapServiceResult($this->getProductsService()->getProducts($params));
    }

    public function addProduct(array $params = []): array
    {
        return $this->wrapServiceResult($this->getProductsService()->addProduct($params));
    }

    public function updateProduct(array $params = []): array
    {
        return $this->wrapServiceResult($this->getProductsService()->updateProduct($params));
    }

    public function deleteProduct(array $params = []): array
    {
        return $this->wrapServiceResult($this->getProductsService()->deleteProduct($params));
    }

    /**
     * Get order logs (history)
     * GET /api/mgr/orders/{id}/logs
     */
    public function getLogs(array $params = []): array
    {
        $id = (int)($params['id'] ?? 0);
        if (!$id) {
            return Response::error('Order ID is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $c = $this->modx->newQuery(msOrderLog::class);
        $c->where(['order_id' => $id]);

        // Filter by visibility if requested (for customer-facing views)
        if (!empty($params['visible_only'])) {
            $c->where(['visible' => true]);
        }

        $c->sortby('timestamp', 'DESC');

        $logs = [];
        $collection = $this->modx->getIterator(msOrderLog::class, $c);
        foreach ($collection as $log) {
            $data = $log->toArray();

            // Process entry - decode JSON if needed
            if (isset($data['entry'])) {
                if (is_string($data['entry'])) {
                    $decoded = json_decode($data['entry'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['entry_data'] = $decoded;
                    } else {
                        // Legacy string format - wrap in array
                        $data['entry_data'] = ['value' => $data['entry']];
                    }
                } elseif (is_array($data['entry'])) {
                    $data['entry_data'] = $data['entry'];
                }
            }

            // Add user name
            if ($data['user_id']) {
                $user = $this->modx->getObject(modUser::class, $data['user_id']);
                $data['user_name'] = $user ? $user->get('username') : 'Unknown';
            }

            $logs[] = $data;
        }

        return Response::success(['results' => $logs])->getData();
    }

    public function getFilters(array $params = []): array
    {
        return Response::success($this->getListService()->getFilters())->getData();
    }

    /**
     * @param array{success: bool, message?: string, data?: array, status?: int} $result
     */
    protected function wrapServiceResult(array $result): array
    {
        $success = !empty($result['success']);
        $message = (string)($result['message'] ?? '');
        $data = array_key_exists('data', $result) ? $result['data'] : [];
        $status = (int)($result['status'] ?? HttpStatus::BAD_REQUEST);

        if ($success) {
            return Response::success($data, $message)->getData();
        }

        $errorPayload = [];
        if (isset($data['fields']) && is_array($data['fields'])) {
            $errorPayload = $data['fields'];
        } elseif (!empty($data)) {
            $errorPayload = $data;
        }

        return Response::error($message, $status, $errorPayload)->getData();
    }

    protected function getPresenter(): ManagerOrderPresenter
    {
        if ($this->presenter === null) {
            if ($this->modx->services->has('ms3_manager_order_presenter')) {
                $this->presenter = $this->modx->services->get('ms3_manager_order_presenter');
            } else {
                $this->presenter = new ManagerOrderPresenter($this->modx);
            }
        }

        return $this->presenter;
    }

    protected function getListService(): ManagerOrderListService
    {
        if ($this->listService === null) {
            if ($this->modx->services->has('ms3_manager_order_list')) {
                $this->listService = $this->modx->services->get('ms3_manager_order_list');
            } else {
                $this->listService = new ManagerOrderListService($this->modx, $this->getPresenter());
            }
        }

        return $this->listService;
    }

    protected function getMutationService(): ManagerOrderMutationService
    {
        if ($this->mutationService === null) {
            if ($this->modx->services->has('ms3_manager_order_mutation')) {
                $this->mutationService = $this->modx->services->get('ms3_manager_order_mutation');
            } else {
                $this->mutationService = new ManagerOrderMutationService(
                    $this->modx,
                    $this->getPresenter(),
                    $this->getOrderLog()
                );
            }
        }

        return $this->mutationService;
    }

    protected function getProductsService(): ManagerOrderProductsService
    {
        if ($this->productsService === null) {
            if ($this->modx->services->has('ms3_manager_order_products')) {
                $this->productsService = $this->modx->services->get('ms3_manager_order_products');
            } else {
                $this->productsService = new ManagerOrderProductsService($this->modx, $this->getOrderLog());
            }
        }

        return $this->productsService;
    }

    protected function getOrderCostRecalculator(): ManagerOrderCostRecalculator
    {
        if ($this->costRecalculator === null) {
            /** @var ManagerOrderCostRecalculator $recalculator */
            $recalculator = $this->modx->services->get('ms3_manager_order_cost_recalculator');
            $this->costRecalculator = $recalculator;
        }

        return $this->costRecalculator;
    }

    protected function getOrderLog(): OrderLogService
    {
        if ($this->orderLog === null) {
            if ($this->modx->services->has('ms3_order_log')) {
                $this->orderLog = $this->modx->services->get('ms3_order_log');
            } else {
                /** @var MiniShop3 $ms3 */
                $ms3 = $this->modx->services->get('ms3');
                $this->orderLog = new OrderLogService($this->modx, $ms3);
            }
        }

        return $this->orderLog;
    }
}
