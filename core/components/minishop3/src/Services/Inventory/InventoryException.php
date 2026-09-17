<?php

namespace MiniShop3\Services\Inventory;

use RuntimeException;
use Throwable;

/**
 * Inventory operation failed. Callers map getLexiconKey() through $modx->lexicon().
 */
class InventoryException extends RuntimeException
{
    /**
     * @param array<string, mixed> $placeholders
     */
    public function __construct(
        private readonly string $lexiconKey,
        private readonly array $placeholders = [],
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $lexiconKey, $code, $previous);
    }

    public function getLexiconKey(): string
    {
        return $this->lexiconKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }
}
