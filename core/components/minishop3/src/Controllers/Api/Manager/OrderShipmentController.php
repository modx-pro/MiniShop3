<?php

declare(strict_types=1);

namespace MiniShop3\Controllers\Api\Manager;

use MiniShop3\Model\msOrder;
use MiniShop3\Router\ApiErrorCode;
use MiniShop3\Router\HttpStatus;
use MiniShop3\Router\Response;
use MiniShop3\Services\Shipment\ShipmentLifecycleException;
use MiniShop3\Services\Shipment\ShipmentLifecycleService;
use MiniShop3\Services\Shipment\ShipmentPublicDto;
use MiniShop3\Services\Shipment\ShipmentStatus;
use MODX\Revolution\modX;

/**
 * Manager REST for a single shipment per order (#607).
 *
 * GET  /api/mgr/orders/{id}/shipment
 * PUT  /api/mgr/orders/{id}/shipment
 */
class OrderShipmentController
{
    public function __construct(protected modX $modx)
    {
        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function get(array $params = []): array
    {
        $orderId = $this->orderId($params);
        if ($orderId === null) {
            return $this->fail('ms3_err_order_nf', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }
        if ($this->loadOrder($orderId) === null) {
            return $this->fail('ms3_err_order_nf', HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }
        $lifecycle = $this->lifecycle();
        if ($lifecycle === null) {
            return $this->fail('ms3_err_unknown', HttpStatus::INTERNAL_SERVER_ERROR, ApiErrorCode::INTERNAL_ERROR);
        }
        $row = $lifecycle->findByOrderId($orderId);

        return Response::success($this->payload($row))->getData();
    }

    /**
     * Create if missing, then optional tracking / status.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function save(array $params = []): array
    {
        $orderId = $this->orderId($params);
        if ($orderId === null) {
            return $this->fail('ms3_err_order_nf', HttpStatus::BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }
        if ($this->loadOrder($orderId) === null) {
            return $this->fail('ms3_err_order_nf', HttpStatus::NOT_FOUND, ApiErrorCode::NOT_FOUND);
        }
        $lifecycle = $this->lifecycle();
        if ($lifecycle === null) {
            return $this->fail('ms3_err_unknown', HttpStatus::INTERNAL_SERVER_ERROR, ApiErrorCode::INTERNAL_ERROR);
        }

        try {
            $row = $lifecycle->findByOrderId($orderId) ?? $lifecycle->create($orderId);
            $tracking = trim((string) ($params['tracking_number'] ?? ''));
            if ($tracking !== '') {
                $row = $lifecycle->setTracking((int) $row['id'], $tracking);
            }
            $status = trim((string) ($params['status'] ?? ''));
            if ($status !== '' && $status !== $row['status']) {
                $row = $lifecycle->transition((int) $row['id'], $status);
            }
        } catch (ShipmentLifecycleException $exception) {
            return $this->fromLifecycle($exception);
        }

        return Response::success($this->payload($row))->getData();
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{shipment: array<string, mixed>|null, statuses: list<string>}
     */
    private function payload(?array $row): array
    {
        return [
            'shipment' => $row !== null ? ShipmentPublicDto::fromRow($row) : null,
            'statuses' => ShipmentStatus::all(),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function orderId(array $params): ?int
    {
        $orderId = (int) ($params['id'] ?? 0);

        return $orderId > 0 ? $orderId : null;
    }

    private function loadOrder(int $orderId): ?msOrder
    {
        $order = $this->modx->getObject(msOrder::class, $orderId);

        return $order instanceof msOrder ? $order : null;
    }

    private function lifecycle(): ?ShipmentLifecycleService
    {
        if (!$this->modx->services->has('ms3_shipment_lifecycle')) {
            return null;
        }
        $lifecycle = $this->modx->services->get('ms3_shipment_lifecycle');

        return $lifecycle instanceof ShipmentLifecycleService ? $lifecycle : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fromLifecycle(ShipmentLifecycleException $exception): array
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
        )->getData();
    }

    /**
     * @return array<string, mixed>
     */
    private function fail(string $message, int $status, string $errorCode): array
    {
        return Response::error($this->lexicon($message), $status, null, $errorCode)->getData();
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
}
