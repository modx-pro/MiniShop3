<?php

namespace MiniShop3\Controllers\Api;

use MiniShop3\Router\Response;
use MiniShop3\Utils\IntArrayDecoder;
use MODX\Revolution\modX;

/**
 * Base API controller
 */
abstract class BaseApiController
{
    /** @var modX */
    protected $modx;

    /** @var \MiniShop3\MiniShop3 */
    protected $ms3;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->ms3 = $modx->services->get('ms3');
    }

    /**
     * Get request parameters
     */
    protected function getRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'GET':
                return $_GET;

            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

                if (strpos($contentType, 'application/json') !== false) {
                    $rawData = file_get_contents('php://input');
                    $data = json_decode($rawData, true);
                    return $data ?: [];
                }

                return $_POST;

            default:
                return [];
        }
    }

    /**
     * Get current user ID
     */
    protected function getUserId(): int
    {
        return (int) $this->modx->user->get('id');
    }

    /**
     * Check if user is authenticated
     */
    protected function isAuthenticated(string $context = 'web'): bool
    {
        return $this->modx->user && $this->modx->user->isAuthenticated($context);
    }

    /**
     * @param mixed $input JSON array, comma-separated string, or array of ids
     * @return list<int> Deduplicated positive ints.
     */
    protected function decodeIntArray($input): array
    {
        return IntArrayDecoder::decode($input);
    }
}
