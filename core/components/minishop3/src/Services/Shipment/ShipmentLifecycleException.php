<?php

declare(strict_types=1);

namespace MiniShop3\Services\Shipment;

use RuntimeException;

final class ShipmentLifecycleException extends RuntimeException
{
    public const KIND_INVALID = 'invalid';
    public const KIND_NOT_FOUND = 'not_found';
    public const KIND_CONFLICT = 'conflict';

    /**
     * @param array<string, scalar|null> $placeholders
     */
    public function __construct(
        private readonly string $lexiconKey,
        private readonly array $placeholders = [],
        private readonly string $kind = self::KIND_INVALID,
    ) {
        parent::__construct($lexiconKey);
    }

    public function getLexiconKey(): string
    {
        return $this->lexiconKey;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getKind(): string
    {
        return $this->kind;
    }
}
