<?php

/**
 * Minimal modX-shaped stub for requiring phinx.php without a live MODX.
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $options Values returned by getOption($key).
 */
function ms3_create_modx_phinx_stub(array $options, ?object $pdo = null): object
{
    return new class ($options, $pdo) {
        /**
         * @param array<string, mixed> $options
         */
        public function __construct(
            private readonly array $options,
            public readonly ?object $pdo,
        ) {
        }

        public function getOption(string $key, mixed $options = null, mixed $default = null): mixed
        {
            return $this->options[$key] ?? $default;
        }

        public function log(int $level, string $message): void
        {
        }
    };
}
