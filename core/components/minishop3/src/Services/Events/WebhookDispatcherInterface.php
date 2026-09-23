<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

interface WebhookDispatcherInterface
{
    public function dispatch(DomainEvent $event): void;
}
