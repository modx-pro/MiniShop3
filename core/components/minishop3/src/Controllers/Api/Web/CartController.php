<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API controller for cart operations (Web API)
 *
 * Thin wrapper over Cart controller for REST API endpoints.
 * Extracts parameters from HTTP request and passes them to Cart.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CartController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->modx->lexicon->load('minishop3:customer', 'minishop3:default');
    }

    /**
     * Add product to cart
     * POST /api/v1/cart/add
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function add(array $params = []): array
    {
        $input = $this->getRequestData();

        $id = (int)($input['id'] ?? 0);
        $count = (int)($input['count'] ?? 1);
        $options = $input['options'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->add($id, $count, $options);

        return $this->transformResponse($result);
    }

    /**
     * Change product quantity
     * POST /api/v1/cart/change
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function change(array $params = []): array
    {
        $input = $this->getRequestData();

        $product_key = $input['product_key'] ?? '';
        $count = (int)($input['count'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if ($product_key === '') {
            return Response::error(
                $this->modx->lexicon('ms3_err_product_key_required'),
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->change($product_key, $count);

        return $this->transformResponse($result);
    }

    /**
     * Change product options in cart
     * POST /api/v1/cart/change-option
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function changeOption(array $params = []): array
    {
        $input = $this->getRequestData();

        $product_key = $input['product_key'] ?? '';
        $options = $input['options'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if ($product_key === '') {
            return Response::error(
                $this->modx->lexicon('ms3_err_product_key_required'),
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        if (!is_array($options) || $options === []) {
            return Response::error(
                $this->modx->lexicon('ms3_cart_change_options_error'),
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->changeOption($product_key, $options);

        return $this->transformResponse($result);
    }

    /**
     * Remove product from cart
     * POST /api/v1/cart/remove
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function remove(array $params = []): array
    {
        $input = $this->getRequestData();

        $product_key = $input['product_key'] ?? '';
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        if ($product_key === '') {
            return Response::error(
                $this->modx->lexicon('ms3_err_product_key_required'),
                HttpStatus::BAD_REQUEST
            )->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->remove($product_key);

        return $this->transformResponse($result);
    }

    /**
     * Get cart
     * GET /api/v1/cart/get
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function get(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->get();

        return $this->transformResponse($result);
    }

    /**
     * Clean cart
     * POST /api/v1/cart/clean
     *
     * @param array $params URL parameters
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function clean(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if ($token === '') {
            return $this->tokenRequiredError();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->clean();

        return $this->transformResponse($result);
    }

    /**
     * @return array{success: bool, message: string, code: int, errors: mixed}
     */
    private function tokenRequiredError(): array
    {
        return Response::error(
            $this->modx->lexicon('ms3_customer_err_token_required'),
            HttpStatus::UNAUTHORIZED
        )->getData();
    }

    /**
     * Get request data (POST/GET)
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
     * Transform Cart response to API format
     *
     * @param array $result Response from Cart controller
     * @return array Response in API format ['success' => bool, 'message' => '', 'data' => [...]]
     */
    protected function transformResponse(array $result): array
    {
        $input = $this->getRequestData();
        $renderTokens = $input['render'] ?? null;

        if (!empty($renderTokens) && $result['success']) {
            $customerToken = $_REQUEST['ms3_token'] ?? '';

            $renderedHtml = $this->renderSnippets($renderTokens, $customerToken);
            if (!empty($renderedHtml)) {
                $result['data']['render'] = $renderedHtml;
            }
        }

        if ($result['success']) {
            return Response::success($result['data'], $result['message'] ?? '')->getData();
        } else {
            return Response::error(
                $result['message'] ?? $this->modx->lexicon('ms3_err_unknown'),
                HttpStatus::BAD_REQUEST,
                $result['data'] ?? []
            )->getData();
        }
    }

    /**
     * Render HTML for cart snippets (SSR)
     *
     * @param string|array $renderTokens Snippet tokens (JSON string or array)
     * @param string $customerToken Customer token for cart access
     * @return array Array ["token" => "html", ...]
     */
    protected function renderSnippets($renderTokens, string $customerToken = ''): array
    {
        if (is_string($renderTokens)) {
            $tokens = json_decode($renderTokens, true);
            if (!is_array($tokens)) {
                return [];
            }
        } else {
            $tokens = $renderTokens;
        }

        if (empty($tokens)) {
            return [];
        }

        /** @var \MiniShop3\Services\TokenService $tokenService */
        $tokenService = $this->modx->services->get('ms3_token_service');

        $rendered = [];

        foreach ($tokens as $token) {
            $snippetParams = $tokenService->getSnippetData($token);

            if (empty($snippetParams)) {
                $this->modx->log(
                    \MODX\Revolution\modX::LOG_LEVEL_WARN,
                    "[MiniShop3] Snippet parameters not found for token: {$token}"
                );
                continue;
            }

            if (!empty($customerToken)) {
                $snippetParams['customer_token'] = $customerToken;
            }

            $snippetName = $snippetParams['_snippetName'] ?? 'msCart';
            unset($snippetParams['_snippetName']);

            $html = $this->modx->runSnippet($snippetName, $snippetParams);

            if (!empty($html)) {
                $rendered[$token] = $html;
            }
        }

        return $rendered;
    }
}
