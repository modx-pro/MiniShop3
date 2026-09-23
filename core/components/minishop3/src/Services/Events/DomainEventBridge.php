<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

use MODX\Revolution\modX;

/**
 * Fan-out domain events to in-process listeners and the outbound webhook dispatcher.
 *
 * Failures are logged and never rethrown — callers must not roll back committed work.
 *
 * Addons register listeners via {@see addListener()} on the resolved `ms3_domain_events`
 * instance. Do not replace the service class in ms3.services.php: DomainEventBridge is
 * final and registered without an interface, so ServiceRegistry would silently fall back.
 * Outbound HTTP is the replaceable `ms3_webhook_dispatcher` (WebhookDispatcherInterface).
 */
final class DomainEventBridge
{
    /**
     * @param list<DomainEventListenerInterface> $listeners
     */
    public function __construct(
        private modX $modx,
        private WebhookDispatcherInterface $dispatcher,
        private array $listeners = [],
    ) {
    }

    /**
     * Register an in-process listener for subsequent {@see emit()} calls.
     */
    public function addListener(DomainEventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    public function emit(DomainEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener->handle($event);
            } catch (\Throwable $e) {
                $this->logFailure('listener', $event, $e);
            }
        }

        try {
            $this->dispatcher->dispatch($event);
        } catch (\Throwable $e) {
            $this->logFailure('dispatcher', $event, $e);
        }
    }

    private function logFailure(string $role, DomainEvent $event, \Throwable $e): void
    {
        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[DomainEventBridge] ' . $role . ' failed for ' . $event->eventType() . ': ' . $e->getMessage()
        );
    }
}
