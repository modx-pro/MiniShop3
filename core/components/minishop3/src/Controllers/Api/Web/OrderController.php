<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for working with orders (Web API)
 *
 * Thin wrapper over Order controller for REST API endpoints.
 * Extracts parameters from HTTP request and passes them to Order.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class OrderController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Get draft order
     * GET /api/v1/order/get
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function get(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->get();

        return $this->transformResponse($result);
    }

    /**
     * Add/update order field
     * POST /api/v1/order/add
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function add(array $params = []): array
    {
        $input = $this->getRequestData();

        $key = $input['key'] ?? '';
        $value = $input['value'] ?? null;
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        if (empty($key)) {
            return Response::error('Field key is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->add($key, $value);

        return $this->transformResponse($result);
    }

    /**
     * Set multiple order fields
     * POST /api/v1/order/set
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function set(array $params = []): array
    {
        $input = $this->getRequestData();

        $fields = $input['fields'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        if (empty($fields) || !is_array($fields)) {
            return Response::error('Fields array is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->set($fields);

        return $this->transformResponse($result);
    }

    /**
     * Remove order field
     * POST /api/v1/order/remove
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function remove(array $params = []): array
    {
        $input = $this->getRequestData();

        $key = $input['key'] ?? '';
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        if (empty($key)) {
            return Response::error('Field key is required', HttpStatus::BAD_REQUEST)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $exists = $order->remove($key);

        if ($exists) {
            return Response::success(['removed' => $key], 'Field removed successfully')->getData();
        } else {
            return Response::error('Field not found', HttpStatus::NOT_FOUND)->getData();
        }
    }

    /**
     * Submit order
     * POST /api/v1/order/submit
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function submit(array $params = []): array
    {
        $input = $this->getRequestData();

        $data = $input['data'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->submit($data);

        return $this->transformResponse($result);
    }

    /**
     * Clean order
     * POST /api/v1/order/clean
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function clean(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->clean();

        return $this->transformResponse($result);
    }

    /**
     * Get total order cost (cart + delivery + payment)
     * GET /api/v1/order/cost
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getCost(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getCost(false);

        return $this->transformResponse($result);
    }

    /**
     * Get cart cost
     * GET /api/v1/order/cost/cart
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getCartCost(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getCartCost();

        return $this->transformResponse($result);
    }

    /**
     * Get delivery cost
     * GET /api/v1/order/cost/delivery
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getDeliveryCost(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryCost();

        return $this->transformResponse($result);
    }

    /**
     * Get payment cost
     * GET /api/v1/order/cost/payment
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getPaymentCost(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getPaymentCost();

        return $this->transformResponse($result);
    }

    /**
     * Set customer address from saved addresses
     * POST /api/v1/order/address/set
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function setCustomerAddress(array $params = []): array
    {
        $input = $this->getRequestData();

        $addressHash = $input['address_hash'] ?? null;
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->setCustomerAddress($addressHash);

        return $this->transformResponse($result);
    }

    /**
     * Clean customer address
     * POST /api/v1/order/address/clean
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function cleanCustomerAddress(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->cleanCustomerAddress();

        return $this->transformResponse($result);
    }

    /**
     * Get validation rules for delivery
     * GET /api/v1/order/delivery/validation-rules
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getDeliveryValidationRules(array $params = []): array
    {
        $input = $this->getRequestData();

        $delivery_id = (int)($input['delivery_id'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryValidationRules($delivery_id);

        return $this->transformResponse($result);
    }

    /**
     * Get required fields for delivery
     * GET /api/v1/order/delivery/required-fields
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function getDeliveryRequiresFields(array $params = []): array
    {
        $input = $this->getRequestData();

        $delivery_id = (int)($input['delivery_id'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', HttpStatus::UNAUTHORIZED)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryRequiresFields($delivery_id);

        return $this->transformResponse($result);
    }

    /**
     * Get data from request (POST/GET)
     *
     * @return array
     */
    protected function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array_merge($_GET, $_POST);
    }

    /**
     * Transform Order response to API format
     *
     * @param array $result Response from Order controller
     * @return array Response in API format ['success' => bool, 'message' => '', 'data' => [...]]
     */
    protected function transformResponse(array $result): array
    {
        if ($result['success']) {
            return Response::success($result['data'], $result['message'] ?? '')->getData();
        } else {
            return Response::error($result['message'] ?? 'Unknown error', HttpStatus::BAD_REQUEST, $result['data'] ?? [])->getData();
        }
    }
}
