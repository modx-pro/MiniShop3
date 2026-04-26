<?php

namespace MiniShop3\Router\Middleware;

use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MODX\Revolution\modX;

/**
 * Middleware for user authentication check
 *
 * For mgr context additionally verifies HTTP_MODAUTH token
 * as MODX does in connector requests (see modConnectorResponse)
 */
class AuthMiddleware implements MiddlewareInterface
{
    protected $modx;
    protected $context;

    /**
     * @param modX $modx
     * @param string $context Context (mgr or web)
     */
    public function __construct(modX $modx, string $context = 'mgr')
    {
        $this->modx = $modx;
        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $params)
    {
        if (!$this->modx->user || !$this->modx->user->isAuthenticated($this->context)) {
            return Response::error('Unauthorized. Please log in.', HttpStatus::UNAUTHORIZED);
        }

        if ($this->context === 'mgr') {
            return $this->validateModAuthToken();
        }

        return null;
    }

    /**
     * Validate HTTP_MODAUTH token (as in MODX connector security)
     *
     * @return Response|null Returns Response with error if token is invalid, otherwise null
     */
    protected function validateModAuthToken()
    {
        $contextKey = $this->modx->context->get('key');
        $expectedToken = $this->modx->user->getUserToken($contextKey);

        $providedToken = $_SERVER['HTTP_MODAUTH'] ?? $_REQUEST['HTTP_MODAUTH'] ?? null;

        if (!$providedToken) {
            return Response::error('Missing HTTP_MODAUTH token', HttpStatus::UNAUTHORIZED);
        }

        if (!hash_equals($expectedToken, $providedToken)) {
            return Response::error('Invalid HTTP_MODAUTH token', HttpStatus::UNAUTHORIZED);
        }

        return null;
    }
}
