<?php

/**
 * #858: msGallery / msOptions / msProductOptions must survive a missing product or a missing
 * resource context. They used to dereference $product->id / $modx->resource->id before the
 * instanceof check, which is a fatal error on PHP 8.
 *
 * Standalone (no MODX/MySQL). Run: php tests/SnippetMissingProductGuardTest.php
 */

declare(strict_types=1);

namespace MiniShop3\Model {
    class msProduct
    {
        public $id;

        public function __construct(int $id = 0)
        {
            $this->id = $id;
        }
    }
}

namespace MiniShop3 {
    class MiniShop3
    {
        public function initialize(string $context): void
        {
        }
    }
}

namespace MiniShop3\Services\Catalog {
    class CatalogResourceGroupVisibility
    {
        public $modx;

        public function __construct($modx)
        {
            $this->modx = $modx;
        }

        public function isVisibleForRequest(int $id): bool
        {
            return true;
        }
    }
}

namespace ModxPro\PdoTools {
    class Fetch
    {
        public function addTime(string $label): void
        {
        }
    }

    class CoreTools
    {
        public function getChunk($tpl, array $props = []): string
        {
            return '';
        }
    }
}

namespace MODX\Revolution {
    class modX
    {
        public const LOG_LEVEL_ERROR = 1;

        public $resource = null;
        public $context;
        public $services;
        public array $logs = [];
        public array $lookups = [];

        public function __construct()
        {
            $this->context = new class {
                public $key = 'web';
            };
            $this->services = new class {
                public function has(string $key): bool
                {
                    return true;
                }

                public function get(string $key): object
                {
                    return new class {
                        public function initialize(string $context): void
                        {
                        }

                        public function addTime(string $label): void
                        {
                        }

                        public function sortOptionValues($options, $sort = null)
                        {
                            return $options;
                        }

                        public function getChunk($tpl, array $props = []): string
                        {
                            return '';
                        }
                    };
                }
            };
        }

        public function getOption($key, array $props = [], $default = null, $skipEmpty = false)
        {
            return array_key_exists($key, $props) && $props[$key] !== '' ? $props[$key] : $default;
        }

        public function getObject($class, array $criteria = [])
        {
            $this->lookups[] = [$class, $criteria];

            return null;
        }

        public function log($level, string $message): void
        {
            $this->logs[] = $message;
        }

        public function lexicon($key, array $props = []): string
        {
            return $key . ':' . ($props['id'] ?? '');
        }
    }
}

namespace {
    $component = dirname(__Dir__);

    $fail = static function (string $message): never {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    };

    $assertTrue = static function (bool $condition, string $case) use ($fail): void {
        if (!$condition) {
            $fail($case);
        }
    };

    set_error_handler(static function (int $errno, string $errstr): bool {
        throw new \ErrorException($errstr, 0, $errno);
    });

    /** @return array{0: mixed, 1: \MODX\Revolution\modX} */
    $runSnippet = static function (string $snippet, $resource, $product) use ($component, $fail): array {
        $modx = new \MODX\Revolution\modX();
        $modx->resource = $resource;
        $scriptProperties = [];
        $input = null;

        try {
            $result = include $component . '/elements/snippets/' . $snippet . '.php';
        } catch (\Throwable $e) {
            $fail($snippet . ' threw ' . get_class($e) . ': ' . $e->getMessage());
        }

        return [$result, $modx];
    };

    $snippets = ['ms3_gallery', 'ms3_options', 'ms3_product_options'];

    foreach ($snippets as $snippet) {
        // Missing product id and no resource in context — the original code died on
        // $modx->resource->id and then on $product->id.
        [$result, $modx] = $runSnippet($snippet, null, 999999);
        if ($snippet === 'ms3_gallery') {
            $assertTrue($result === '', $snippet . ' returns empty string for a missing product');
            $assertTrue(
                in_array('[msGallery] Resource 999999 is not msProduct', $modx->logs, true),
                $snippet . ' logs the requested product id'
            );
        } else {
            $assertTrue($result === 'ms3_err_options_is_not_msproduct:999999', $snippet . ' returns the lexicon with the requested id');
        }
        $assertTrue(
            $modx->lookups !== [] && $modx->lookups[0][1] === ['id' => 999999],
            $snippet . ' loads the requested product by id'
        );

        // Snippet called with no product and no resource at all.
        [$result, $modx] = $runSnippet($snippet, null, null);
        if ($snippet === 'ms3_gallery') {
            $assertTrue($result === '', $snippet . ' returns empty string without a resource');
            $assertTrue(
                in_array('[msGallery] Resource 0 is not msProduct', $modx->logs, true),
                $snippet . ' logs a safe id without a resource'
            );
        } else {
            $assertTrue($result === 'ms3_err_options_is_not_msproduct:0', $snippet . ' returns the lexicon without a resource');
        }

        // Product passed as an object must be reduced to its id without an int-cast warning.
        [$result, $modx] = $runSnippet(
            $snippet,
            new \MiniShop3\Model\msProduct(5),
            new \MiniShop3\Model\msProduct(999999)
        );
        $assertTrue(
            $modx->lookups !== [] && $modx->lookups[0][1] === ['id' => 999999],
            $snippet . ' accepts an object product by its id'
        );

        // The current resource is not a product: no fatal on its id either.
        [$result] = $runSnippet($snippet, (object) ['id' => 5], 0);
        if ($snippet === 'ms3_gallery') {
            $assertTrue($result === '', $snippet . ' returns empty string for a non-product resource');
        } else {
            $assertTrue(is_string($result), $snippet . ' returns a string for a non-product resource');
        }
    }

    restore_error_handler();

    fwrite(STDOUT, "OK SnippetMissingProductGuardTest\n");
    exit(0);
}
