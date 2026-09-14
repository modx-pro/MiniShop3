<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Web;

use MiniShop3\Controllers\Delivery\ShipmentProviderInterface;
use MiniShop3\Model\msDelivery;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Delivery\DeliveryService;
use MiniShop3\Services\Shipment\ShipmentLifecycleException;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MODX\Revolution\modX;

/**
 * Public async delivery callback. Auth is provider signature, not customer token.
 */
class DeliveryWebhookController
{
    public function __construct(protected modX $modx)
    {
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * POST /api/v1/delivery/webhook/{delivery_id}
     *
     * @param array<string, mixed> $params
     */
    public function handle(array $params = []): Response
    {
        if (!ShipmentLifecycleService::isEnabled($this->modx)) {
            return $this->fail('ms3_err_shipment_disabled', HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        $deliveryId = (int) ($params['delivery_id'] ?? 0);
        if ($deliveryId <= 0) {
            return $this->fail('ms3_err_delivery_id_required', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        $method = $this->modx->getObject(msDelivery::class, ['id' => $deliveryId]);
        if (!$method instanceof msDelivery) {
            return $this->fail('ms3_err_delivery_nf', HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }

        /** @var DeliveryService $deliveryService */
        $deliveryService = $this->modx->services->get('ms3_delivery_service');
        $handler = $deliveryService->loadDeliveryController($method);
        if (!$handler instanceof ShipmentProviderInterface) {
            return $this->fail(
                'ms3_err_shipment_webhook_unsupported',
                HttpStatus::BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        }

        $rawBody = $this->readRawRequestBody();
        $payload = $this->decodeJsonObject($rawBody);
        if ($payload === null) {
            return $this->fail('ms3_err_shipment_webhook_invalid', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }
        $headers = $this->requestHeaders();
        if (!$handler->verifyWebhook($rawBody, $payload, $headers, $method)) {
            return $this->fail(
                'ms3_err_shipment_webhook_unauthorized',
                HttpStatus::UNAUTHORIZED,
                ApiErrorCode::UNAUTHORIZED
            );
        }

        $event = $handler->parseWebhook($payload, $headers);
        if ($event === null) {
            return $this->fail('ms3_err_shipment_webhook_invalid', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        /** @var ShipmentLifecycleService $lifecycle */
        $lifecycle = $this->modx->services->get('ms3_shipment_lifecycle');
        $class = $method->get('class');
        $provider = is_string($class) && $class !== '' ? $class : $handler::class;

        try {
            $shipment = $lifecycle->applyProviderEvent($event, $deliveryId, $provider);
        } catch (ShipmentLifecycleException $exception) {
            return $this->fromLifecycle($exception);
        } catch (\Throwable $exception) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'Delivery webhook failed: ' . $exception->getMessage()
            );

            return $this->fail(
                'ms3_err_unknown',
                HttpStatus::INTERNAL_SERVER_ERROR,
                ApiErrorCode::INTERNAL_ERROR
            );
        }

        return Response::success([
            'shipment_id' => $shipment['id'],
            'status' => $shipment['status'],
            'order_id' => $shipment['order_id'],
            'tracking_number' => $shipment['tracking_number'],
        ]);
    }

    private function fromLifecycle(ShipmentLifecycleException $exception): Response
    {
        [$status, $errorCode] = match ($exception->getKind()) {
            ShipmentLifecycleException::KIND_CONFLICT => [HttpStatus::CONFLICT, ApiErrorCode::CONFLICT],
            ShipmentLifecycleException::KIND_NOT_FOUND => [HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND],
            default => [HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST],
        };

        return Response::error(
            $this->lexicon($exception->getLexiconKey(), $exception->getPlaceholders(), $exception->getMessage()),
            $status,
            null,
            $errorCode
        );
    }

    private function fail(string $message, int $status, string $errorCode): Response
    {
        return Response::error($this->lexicon($message), $status, null, $errorCode);
    }

    /**
     * @param array<string, scalar|null> $placeholders
     */
    private function lexicon(string $key, array $placeholders = [], ?string $fallback = null): string
    {
        $message = $this->modx->lexicon($key, $placeholders);
        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $fallback ?? $key;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function readRawRequestBody(): string
    {
        $raw = file_get_contents('php://input');

        return is_string($raw) ? $raw : '';
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (!is_string($key) || !str_starts_with($key, 'HTTP_')) {
                    continue;
                }
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = is_scalar($value) ? (string) $value : '';
            }

            return $headers;
        }
        $normalized = [];
        foreach (getallheaders() as $name => $value) {
            $normalized[strtolower((string) $name)] = (string) $value;
        }

        return $normalized;
    }
}
