<?php

namespace MiniShop3\Services\Order;

use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MODX\Revolution\modX;
use Throwable;

/**
 * Sessionless programmatic order creation for Extras / cron / integrations (#507).
 *
 * Thin orchestrator: draft graph via {@see OrderDraftManager}, finalize via
 * {@see OrderFinalizeService} with `origin=integration`.
 */
class ProgrammaticOrderService
{
    public const PROPERTY_IDEMPOTENCY_KEY = OrderOrigin::PROPERTY_IDEMPOTENCY_KEY;
    public const PROPERTY_ORIGIN = OrderOrigin::PROPERTY_ORIGIN;

    public function __construct(
        protected modX $modx,
        protected MiniShop3 $ms3,
        protected OrderFinalizeService $finalizeService,
        protected OrderDraftManager $draftManager,
    ) {
    }

    /**
     * Create (or return/resume idempotent) finalized order.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, message: string, data: array}
     */
    public function create(array $input): array
    {
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            return $this->error('ms3_order_err_idempotency_key_required');
        }

        $origin = OrderOrigin::normalize(
            $input['origin'] ?? OrderOrigin::INTEGRATION,
            OrderOrigin::INTEGRATION
        );

        $finalizeOptions = [
            'skip_notifications' => !empty($input['skip_notifications']),
            'skip_validation' => !empty($input['skip_validation']),
            'origin' => $origin,
        ];
        if (array_key_exists('delivery_cost', $input)) {
            $finalizeOptions['cost_mode'] = ManagerOrderCostRecalculator::MODE_MANUAL;
            $finalizeOptions['manual_delivery_cost'] = (float) $input['delivery_cost'];
        }

        $existing = $this->findByIdempotencyKey($idempotencyKey);
        if ($existing instanceof msOrder) {
            return $this->resumeOrReturnExisting($existing, $finalizeOptions);
        }

        $products = $input['products'] ?? null;
        if (!is_array($products) || $products === []) {
            return $this->error('ms3_order_err_products_required', ['products']);
        }

        try {
            $order = $this->buildDraftGraph($input, $idempotencyKey, $origin, $products);
        } catch (Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ProgrammaticOrderService] Draft build failed: ' . $e->getMessage()
            );

            return $this->error('ms3_order_err_programmatic_create');
        }

        return $this->finalizeBuiltOrder($order, $finalizeOptions, 'ms3_order_programmatic_created');
    }

    /**
     * @param array<string, mixed> $finalizeOptions
     */
    protected function resumeOrReturnExisting(msOrder $existing, array $finalizeOptions): array
    {
        $statusDraft = (int) $this->modx->getOption('ms3_status_draft', null, 1) ?: 1;
        if ((int) $existing->get('status_id') === $statusDraft) {
            return $this->finalizeBuiltOrder(
                $existing,
                $finalizeOptions,
                'ms3_order_programmatic_created'
            );
        }

        return $this->success('ms3_order_programmatic_idempotent', $this->resultPayload($existing));
    }

    /**
     * @param array<string, mixed> $finalizeOptions
     */
    protected function finalizeBuiltOrder(msOrder $order, array $finalizeOptions, string $successMessage): array
    {
        $finalize = $this->finalizeService->finalize((int) $order->get('id'), $finalizeOptions);
        if (!$finalize['success']) {
            // Keep draft for idempotent resume; do not delete after mid-finalize failures.
            return $finalize;
        }

        $reloaded = $this->modx->getObject(msOrder::class, (int) $order->get('id'));
        if (!$reloaded instanceof msOrder) {
            return $this->error('ms3_order_err_nf');
        }

        return $this->success($successMessage, $this->resultPayload($reloaded));
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $products
     */
    protected function buildDraftGraph(
        array $input,
        string $idempotencyKey,
        string $origin,
        array $products
    ): msOrder {
        $order = $this->draftManager->createSessionlessDraft([
            'context' => $input['context'] ?? 'web',
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'delivery_id' => (int) ($input['delivery_id'] ?? 0),
            'payment_id' => (int) ($input['payment_id'] ?? 0),
            'delivery_cost' => (float) ($input['delivery_cost'] ?? 0),
            'order_comment' => (string) ($input['order_comment'] ?? ''),
            'idempotency_key' => $idempotencyKey,
            'origin' => $origin,
            'properties' => is_array($input['properties'] ?? null) ? $input['properties'] : [],
        ]);

        try {
            $this->draftManager->fillAddressFromArray(
                $order,
                is_array($input['address'] ?? null) ? $input['address'] : []
            );
            foreach ($products as $snapshot) {
                if (!is_array($snapshot)) {
                    throw new \InvalidArgumentException('ms3_order_err_product_snapshot');
                }
                $this->draftManager->addProductFromSnapshot($order, $snapshot, $origin);
            }
            $this->draftManager->recalculate($order);
        } catch (Throwable $e) {
            $this->cleanupDraft($order);
            throw $e;
        }

        return $order;
    }

    protected function findByIdempotencyKey(string $key): ?msOrder
    {
        /** @var msOrder|null $order */
        $order = $this->modx->getObject(msOrder::class, [
            'idempotency_key' => $key,
        ]);

        return $order instanceof msOrder ? $order : null;
    }

    /**
     * @return array{order_id: int, uuid: string|null, num: string|null, status_id: int}
     */
    protected function resultPayload(msOrder $order): array
    {
        return [
            'order_id' => (int) $order->get('id'),
            'uuid' => $order->get('uuid'),
            'num' => $order->get('num'),
            'status_id' => (int) $order->get('status_id'),
        ];
    }

    protected function cleanupDraft(msOrder $order): void
    {
        try {
            if (!$this->draftManager->deleteDraft($order)) {
                $this->modx->log(
                    modX::LOG_LEVEL_ERROR,
                    '[ProgrammaticOrderService] deleteDraft returned false for #'
                    . $order->get('id')
                );
            }
        } catch (Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ProgrammaticOrderService] Failed to cleanup draft #'
                . $order->get('id')
                . ': '
                . $e->getMessage()
            );
        }
    }

    protected function success(string $message = '', array $data = []): array
    {
        return $this->ms3->utils->success($message, $data);
    }

    protected function error(string $message, array $data = []): array
    {
        return $this->ms3->utils->error($message, $data);
    }
}
