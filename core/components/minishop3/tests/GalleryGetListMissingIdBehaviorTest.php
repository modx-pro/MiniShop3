<?php

/**
 * Behavioral guard for #901: Gallery\GetList::initialize() must reject a
 * missing product_id, but keep explicit product_id = 0 valid.
 *
 * Standalone smoke test: php tests/GalleryGetListMissingIdBehaviorTest.php
 */

declare(strict_types=1);

namespace MODX\Revolution {
    class modX
    {
        public object $services;

        public function __construct()
        {
            $this->services = new class {
                public function get(string $key): object
                {
                    return new \stdClass();
                }

                public function has(string $key): bool
                {
                    return false;
                }
            };
        }

        public function lexicon(string $key, array $params = []): string
        {
            return $key;
        }

        public function getObject($className, $criteria = null, $cacheFlag = true)
        {
            return null;
        }

        public function getOption($key, $options = null, $default = null, $skipEmpty = false)
        {
            return $default;
        }
    }
}

namespace MODX\Revolution\Processors\Model {
    class GetListProcessor
    {
        public $modx;

        /** @var array<string, mixed> */
        protected array $properties = [];

        public function __construct($modx, array $properties = [])
        {
            $this->modx = $modx;
            $this->properties = $properties;
        }

        public function getProperty($key, $default = null)
        {
            return array_key_exists($key, $this->properties) ? $this->properties[$key] : $default;
        }

        public function initialize()
        {
            return true;
        }
    }
}

namespace MiniShop3\Services\Product {
    class ProductDocumentPolicy
    {
        public const POLICY_VIEW = 'view';
    }
}

namespace {
    use MiniShop3\Processors\Gallery\GetList;

    $fail = static function (string $message): never {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    };

    require_once __DIR__ . '/../src/Processors/Gallery/GuardsProductDocumentTrait.php';
    require_once __DIR__ . '/../src/Processors/Gallery/GetList.php';

    $modx = new \MODX\Revolution\modX();

    $missing = new GetList($modx, []);
    if ($missing->initialize() !== 'ms3_err_product_id_ns') {
        $fail('GetList must fail with ms3_err_product_id_ns when product_id is absent (#901)');
    }

    $typo = new GetList($modx, ['productId' => 42]);
    if ($typo->initialize() !== 'ms3_err_product_id_ns') {
        $fail('GetList must fail with ms3_err_product_id_ns for the productId typo (#901)');
    }

    $zero = new GetList($modx, ['product_id' => 0]);
    if ($zero->initialize() !== true) {
        $fail('GetList must keep explicit product_id = 0 valid (#901)');
    }

    fwrite(STDOUT, "OK GalleryGetListMissingIdBehaviorTest (#901)\n");
    exit(0);
}
