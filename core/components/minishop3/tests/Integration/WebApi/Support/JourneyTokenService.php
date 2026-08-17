<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Integration\WebApi\Support;

/**
 * In-memory TokenService double for Web API Router journey tests.
 */
final class JourneyTokenService
{
    /** @var array<string, array{customer_id: int, expires_at: string}> */
    private array $tokens = [];

    private int $anonSeq = 0;

    public function resolveApiToken(string $token): array
    {
        if ($token === '' || !isset($this->tokens[$token])) {
            return ['token' => null, 'reason' => 'missing'];
        }

        $row = $this->tokens[$token];
        if (strtotime($row['expires_at']) < time()) {
            return ['token' => null, 'reason' => 'expired'];
        }

        return ['token' => $this->tokenRow($row['customer_id'], $row['expires_at']), 'reason' => 'ok'];
    }

    public function sessionTokenBelongsToCustomer(int $customerId): bool
    {
        return $customerId > 0;
    }

    /**
     * @return array{token: string}
     */
    public function generateCustomerToken(): array
    {
        $this->anonSeq++;
        $token = 'anon-journey-' . $this->anonSeq;
        $this->putToken($token, 0);

        return ['token' => $token];
    }

    public function putToken(string $token, int $customerId, ?string $expiresAt = null): void
    {
        $this->tokens[$token] = [
            'customer_id' => $customerId,
            'expires_at' => $expiresAt ?? date('Y-m-d H:i:s', time() + 3600),
        ];
    }

    public function promoteToCustomer(string $token, int $customerId): void
    {
        $this->putToken($token, $customerId);
    }

    public function forget(string $token): void
    {
        unset($this->tokens[$token]);
    }

    private function tokenRow(int $customerId, string $expiresAt): object
    {
        return new class ($customerId, $expiresAt) {
            public function __construct(
                private int $customerId,
                private string $expiresAt,
            ) {
            }

            public function get(string $field): mixed
            {
                return match ($field) {
                    'customer_id' => $this->customerId,
                    'expires_at' => $this->expiresAt,
                    default => null,
                };
            }
        };
    }
}
