<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

/**
 * Invalid public product/list filter params (maps to HTTP 400 + lexicon).
 */
final class ProductCatalogFilterException extends \InvalidArgumentException
{
    public function __construct(
        private readonly string $lexiconKey,
    ) {
        parent::__construct($lexiconKey);
    }

    public function getLexiconKey(): string
    {
        return $this->lexiconKey;
    }
}
