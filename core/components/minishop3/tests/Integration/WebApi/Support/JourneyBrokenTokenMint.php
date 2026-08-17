<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * TokenService double: no token on resolve, auto-mint returns empty string.
 */
final class JourneyBrokenTokenMint
{
    public function resolveApiToken(string $token): array
    {
        unset($token);

        return ['token' => null, 'reason' => 'missing'];
    }

    public function syncSessionFromToken(object $tokenObj): void
    {
        unset($tokenObj);
    }

    /**
     * @return array{token: string}
     */
    public function generateCustomerToken(): array
    {
        return ['token' => ''];
    }
}
