<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

/**
 * Default no-op outbound webhook dispatcher until an addon replaces ms3_webhook_dispatcher.
 *
 * The DI factory passes modX only to replacement classes. This default has no constructor
 * and performs no HTTP.
 */
final class NullWebhookDispatcher implements WebhookDispatcherInterface
{
    public function dispatch(DomainEvent $event): void
    {
    }
}
