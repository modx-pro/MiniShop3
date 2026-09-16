<?php

declare(strict_types=1);

namespace MiniShop3\Services\Payment;

use RuntimeException;
use Throwable;

class PaymentLifecycleException extends RuntimeException
{
    public const KIND_CONFLICT = 'conflict';
    public const KIND_NOT_FOUND = 'not_found';
    public const KIND_INVALID = 'invalid';

    /**
     * @param array<string, mixed> $placeholders
     */
    public function __construct(
        private readonly string $lexiconKey,
        private readonly array $placeholders = [],
        private readonly string $kind = self::KIND_INVALID,
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

    public function getKind(): string
    {
        return $this->kind;
    }
}
