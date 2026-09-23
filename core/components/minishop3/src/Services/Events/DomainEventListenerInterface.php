<?php

declare(strict_types=1);

namespace MiniShop3\Services\Events;

interface DomainEventListenerInterface
{
    public function handle(DomainEvent $event): void;
}
