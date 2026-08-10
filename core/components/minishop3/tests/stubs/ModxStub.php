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
    public const LOG_LEVEL_DEBUG = 4;

    /** @var object|null */
    public $user;

    /** @var object|null */
    public $context;

    /** @var array<string, bool> */
    private array $permissions = [];

    /** @var object|null */
    public $services;

    /** @var object|null */
    public $lexicon;

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

        $this->services = new class {
            public function has(string $key): bool
            {
                return $key === 'ms3';
            }

            public function get(string $key): mixed
            {
                return null;
            }
        };

        $this->lexicon = new class {
            public function load(string ...$topics): void
            {
            }
        };
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $options = null, $default = null)
    {
        return $default;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function lexicon(string $key, array $params = []): string
    {
        return $key;
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
