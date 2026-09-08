<?php

declare(strict_types=1);

namespace MiniShop3\Services\Catalog;

/**
 * Invalid public catalog context param (maps to HTTP 400 + lexicon).
 */
final class CatalogContextException extends \InvalidArgumentException
{
    public const LEXICON_INVALID = 'ms3_err_catalog_context_invalid';

    public function __construct(
        private readonly string $lexiconKey = self::LEXICON_INVALID,
    ) {
        parent::__construct($lexiconKey);
    }

    public static function invalid(): self
    {
        return new self(self::LEXICON_INVALID);
    }

    public function getLexiconKey(): string
    {
        return $this->lexiconKey;
    }
}
