<?php

declare(strict_types=1);

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductLink;
use MODX\Revolution\modX;

/**
 * Synchronizes msProductLink rows from product links payload.
 */
class ProductLinksWriter
{
    use ProductDataExplicitFieldsTrait;

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Save product links when the `links` key was explicitly passed in POST data.
     */
    public function saveLinks(msProductData $productData): void
    {
        $fields = $this->readExplicitFields($productData);
        if (!array_key_exists('links', $fields)) {
            return;
        }

        $links = $fields['links'];
        if (is_string($links)) {
            $links = json_decode($links, true);
        }
        if (!is_array($links)) {
            return;
        }

        $productId = $productData->get('id');
        $this->modx->removeCollection(msProductLink::class, ['master' => $productId]);

        foreach ($links as $link) {
            if (empty($link['slave']) || empty($link['link'])) {
                continue;
            }

            /** @var msProductLink $productLink */
            $productLink = $this->modx->newObject(msProductLink::class);
            $productLink->set('master', $productId);
            $productLink->set('slave', $link['slave']);
            $productLink->set('link', $link['link']);
            $productLink->save();
        }
    }

}
