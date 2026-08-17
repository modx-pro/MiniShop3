<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\DomainMs2Response;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Api\WebApiContextResolver;
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
     * @return Response
     */
    public function add(array $params = []): Response
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
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->add($id, $count, $options);

        return $this->transformResponse($result);
    }

    /**
     * Change product quantity
     * POST /api/v1/cart/change
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function change(array $params = []): Response
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
            );
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->change($product_key, $count);

        return $this->transformResponse($result);
    }

    /**
     * Change product options in cart
     * POST /api/v1/cart/change-option
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function changeOption(array $params = []): Response
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
            );
        }

        if (!is_array($options) || $options === []) {
            return Response::error(
                $this->modx->lexicon('ms3_cart_change_options_error'),
                HttpStatus::BAD_REQUEST
            );
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->changeOption($product_key, $options);

        return $this->transformResponse($result);
    }

    /**
     * Remove product from cart
     * POST /api/v1/cart/remove
     *
     * @param array $params URL parameters
     * @return Response
     */
    public function remove(array $params = []): Response
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
            );
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->remove($product_key);

        return $this->transformResponse($result);
    }

    /**
     * Get cart
     * GET /api/v1/cart/get
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
        $cart = $ms3->cart;
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->get();

        return $this->transformResponse($result);
    }

    /**
     * Clean cart
     * POST /api/v1/cart/clean
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
        $cart = $ms3->cart;
        $cart->initialize($this->pageContextKey(), $token);

        $result = $cart->clean();

        return $this->transformResponse($result);
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
     * Page/API lexicon context (never dereference null $modx->context).
     */
    private function pageContextKey(): string
    {
        return WebApiContextResolver::liveContextKey($this->modx);
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
     * Transform Cart domain MS2-array to Web API Response (#572).
     *
     * @param array<string, mixed> $result
     * @return Response
     */
    protected function transformResponse(array $result): Response
    {
        $input = $this->getRequestData();
        $renderTokens = $input['render'] ?? null;

        if (!empty($renderTokens) && !empty($result['success'])) {
            $customerToken = $_REQUEST['ms3_token'] ?? '';

            $renderedHtml = $this->renderSnippets($renderTokens, $customerToken);
            if (!empty($renderedHtml) && is_array($result['data'] ?? null)) {
                $result['data']['render'] = $renderedHtml;
            }
        }

        return DomainMs2Response::fromDomain(
            $result,
            $this->modx->lexicon('ms3_err_unknown')
        );
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
