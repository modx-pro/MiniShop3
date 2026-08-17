<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * Shared success/error envelope for in-memory journey facades.
 */
final class JourneyResult
{
    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public static function ok(string $message, array $data): array
    {
        return ['success' => true, 'message' => $message, 'data' => $data];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public static function fail(string $message, array $data = []): array
    {
        return ['success' => false, 'message' => $message, 'data' => $data];
    }
}
