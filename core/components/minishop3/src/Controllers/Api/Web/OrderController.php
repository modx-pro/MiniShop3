<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\DomainMs2Response;
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
        $this->modx->lexicon->load('minishop3:customer', 'minishop3:default', 'minishop3:order');
    }

    /**
     * Get draft order
     * GET /api/v1/order/get
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function get(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->get();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Add/update order field
     * POST /api/v1/order/add
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function add(array $params = []): Response
    {
        $input = $this->getRequestData();

        $key = $input['key'] ?? '';
        $value = $input['value'] ?? null;
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if (empty($key)) {
            return Response::error(
                $this->modx->lexicon('ms3_err_field_key_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->add($key, $value);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Set multiple order fields
     * POST /api/v1/order/set
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function set(array $params = []): Response
    {
        $input = $this->getRequestData();

        $fields = $input['fields'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if (empty($fields) || !is_array($fields)) {
            return Response::error($this->modx->lexicon('ms3_err_fields_required'), HttpStatus::BAD_REQUEST);
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->set($fields);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Remove order field
     * POST /api/v1/order/remove
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function remove(array $params = []): Response
    {
        $input = $this->getRequestData();

        $key = $input['key'] ?? '';
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if (empty($key)) {
            return Response::error(
                $this->modx->lexicon('ms3_err_field_key_required'),
                HttpStatus::BAD_REQUEST
            );
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $exists = $order->remove($key);

        if ($exists) {
            return Response::success(['removed' => $key], $this->modx->lexicon('ms3_order_remove_success'));
        }

        return Response::error($this->modx->lexicon('ms3_err_field_nf'), HttpStatus::NOT_FOUND);
    }

    /**
     * Submit order
     * POST /api/v1/order/submit
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function submit(array $params = []): Response
    {
        $input = $this->getRequestData();

        $data = $input['data'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->submit($data);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Clean order
     * POST /api/v1/order/clean
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function clean(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->clean();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get total order cost (cart + delivery + payment)
     * GET /api/v1/order/cost
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getCost(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getCost(false);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get cart cost
     * GET /api/v1/order/cost/cart
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getCartCost(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getCartCost();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get delivery cost
     * GET /api/v1/order/cost/delivery
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getDeliveryCost(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryCost();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get payment cost
     * GET /api/v1/order/cost/payment
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getPaymentCost(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getPaymentCost();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Set customer address from saved addresses
     * POST /api/v1/order/address/set
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function setCustomerAddress(array $params = []): Response
    {
        $input = $this->getRequestData();

        $addressHash = $input['address_hash'] ?? null;
        return $this->setCustomerAddressByHash($addressHash);
    }

    /**
     * Set customer address from CustomerAPI legacy payload.
     * POST /api/v1/customer/changeAddress
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function changeCustomerAddress(array $params = []): Response
    {
        $input = $this->getRequestData();

        $addressHash = $input['address_hash'] ?? ($input['value'] ?? null);
        return $this->setCustomerAddressByHash($addressHash);
    }

    protected function setCustomerAddressByHash(?string $addressHash = null): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->setCustomerAddress($addressHash);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Clean customer address
     * POST /api/v1/order/address/clean
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function cleanCustomerAddress(array $params = []): Response
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->cleanCustomerAddress();

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get validation rules for delivery
     * GET /api/v1/order/delivery/validation-rules
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getDeliveryValidationRules(array $params = []): Response
    {
        $input = $this->getRequestData();

        $delivery_id = (int)($input['delivery_id'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryValidationRules($delivery_id);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * Get required fields for delivery
     * GET /api/v1/order/delivery/required-fields
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function getDeliveryRequiresFields(array $params = []): Response
    {
        $input = $this->getRequestData();

        $delivery_id = (int)($input['delivery_id'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $order = $ms3->order;
        $order->initialize($token);

        $result = $order->getDeliveryRequiresFields($delivery_id);

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
    }

    /**
     * @return Response
     */
    private function tokenRequiredError(): Response
    {
        return Response::errorWithCode(
            ApiErrorCode::TOKEN_REQUIRED,
            $this->modx->lexicon('ms3_customer_err_token_required'),
            HttpStatus::UNAUTHORIZED
        );
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

}
