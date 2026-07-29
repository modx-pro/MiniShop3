<?php

declare(strict_types=1);

namespace MODX\Revolution;

/**
 * Minimal modX stub for standalone router permission tests (no full MODX install).
 */
class modX
{
    public const LOG_LEVEL_ERROR = 1;
    public const LOG_LEVEL_WARN = 2;
    public const LOG_LEVEL_INFO = 3;

    /** @var object|null */
    public $user;

    /** @var object|null */
    public $context;

    /** @var array<string, bool> */
    private array $permissions = [];

    public function __construct()
    {
        $this->context = new class {
            public function get(string $key): string
            {
                return $key === 'key' ? 'mgr' : '';
            }
        };

        $this->user = new class {
            public function isAuthenticated(string $context): bool
            {
                return $context === 'mgr';
            }

            public function getUserToken(string $contextKey): string
            {
                return 'test-modauth-token';
            }
        };
    }

    /**
     * @param list<string> $permissions
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = array_fill_keys($permissions, true);
    }

    public function hasPermission(string $permission): bool
    {
        return !empty($this->permissions[$permission]);
    }

    public function log($level, $message): void
    {
    }
}
