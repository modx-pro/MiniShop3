<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Stubs;

/**
 * Minimal ProcessorResponse stand-in for standalone Web API tests.
 */
final class ProcessorResponseStub
{
    public function __construct(
        private readonly bool $error,
        private readonly string $message = '',
        private readonly mixed $object = null,
    ) {
    }

    public static function success(mixed $object = null, string $message = ''): self
    {
        return new self(false, $message, $object);
    }

    public static function failure(string $message, mixed $object = null): self
    {
        return new self(true, $message, $object);
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getObject(): mixed
    {
        return $this->object;
    }
}
