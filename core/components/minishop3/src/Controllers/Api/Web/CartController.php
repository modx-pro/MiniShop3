<?php

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * API контроллер для работы с корзиной (Web API)
 *
 * Тонкая обёртка над Cart контроллером для REST API endpoints.
 * Извлекает параметры из HTTP запроса и передает их в Cart.
 *
 * @package MiniShop3\Controllers\Api\Web
 */
class CartController
{
    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Добавление товара в корзину
     * POST /api/v1/cart/add
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function add(array $params = []): array
    {
        $input = $this->getRequestData();

        $id = (int)($input['id'] ?? 0);
        $count = (int)($input['count'] ?? 1);
        $options = $input['options'] ?? [];
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', 401)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->add($id, $count, $options);

        return $this->transformResponse($result);
    }

    /**
     * Изменение количества товара
     * POST /api/v1/cart/change
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function change(array $params = []): array
    {
        $input = $this->getRequestData();

        $product_key = $input['product_key'] ?? '';
        $count = (int)($input['count'] ?? 0);
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', 401);
        }

        if (empty($product_key)) {
            return Response::error('Product key is required', 400);
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->change($product_key, $count);

        return $this->transformResponse($result);
    }

    /**
     * Удаление товара из корзины
     * POST /api/v1/cart/remove
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function remove(array $params = []): array
    {
        $input = $this->getRequestData();

        $product_key = $input['product_key'] ?? '';
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', 401);
        }

        if (empty($product_key)) {
            return Response::error('Product key is required', 400);
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->remove($product_key);

        return $this->transformResponse($result);
    }

    /**
     * Получение корзины
     * GET /api/v1/cart/get
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function get(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', 401)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->get();

        return $this->transformResponse($result);
    }

    /**
     * Очистка корзины
     * POST /api/v1/cart/clean
     *
     * @param array $params URL параметры
     * @return array Response ['success' => bool, 'message' => '', 'data' => [...]]
     */
    public function clean(array $params = []): array
    {
        $token = $_REQUEST['ms3_token'] ?? '';

        if (empty($token)) {
            return Response::error('Token is required', 401)->getData();
        }

        $ms3 = $this->modx->services->get('ms3');
        $cart = $ms3->cart;
        $cart->initialize($this->modx->context->key, $token);

        $result = $cart->clean();

        return $this->transformResponse($result);
    }

    /**
     * Получение данных из запроса (POST/GET)
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
     * Преобразование ответа Cart в формат API
     *
     * @param array $result Ответ от Cart контроллера
     * @return array Response в формате API ['success' => bool, 'message' => '', 'data' => [...]]
     */
    protected function transformResponse(array $result): array
    {
        // Проверяем наличие параметра render для SSR
        $input = $this->getRequestData();
        $renderTokens = $input['render'] ?? null;

        // Если запрошен SSR рендер - генерируем HTML
        if (!empty($renderTokens) && $result['success']) {
            // Получаем токен клиента из запроса
            $customerToken = $_REQUEST['ms3_token'] ?? '';

            $renderedHtml = $this->renderSnippets($renderTokens, $customerToken);
            if (!empty($renderedHtml)) {
                $result['data']['render'] = $renderedHtml;
            }
        }

        if ($result['success']) {
            return Response::success($result['data'], $result['message'] ?? '')->getData();
        } else {
            return Response::error($result['message'] ?? 'Unknown error', 400, $result['data'] ?? [])->getData();
        }
    }

    /**
     * Рендер HTML для сниппетов корзины (SSR)
     *
     * @param string|array $renderTokens Токены сниппетов (JSON строка или массив)
     * @param string $customerToken Токен клиента для доступа к корзине
     * @return array Массив ["token" => "html", ...]
     */
    protected function renderSnippets($renderTokens, string $customerToken = ''): array
    {
        // Декодируем токены если пришла строка
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
            // Получаем параметры сниппета из кеша
            $snippetParams = $tokenService->getSnippetData($token);

            if (empty($snippetParams)) {
                $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_WARN,
                    "[MiniShop3] Snippet parameters not found for token: {$token}"
                );
                continue;
            }

            // ВАЖНО: Добавляем токен клиента в параметры сниппета
            // Это позволит сниппету получить правильную корзину
            if (!empty($customerToken)) {
                $snippetParams['customer_token'] = $customerToken;
            }

            // Вызываем сниппет msCart с сохраненными параметрами
            $html = $this->modx->runSnippet('msCart', $snippetParams);

            if (!empty($html)) {
                $rendered[$token] = $html;
            }
        }

        return $rendered;
    }
}
