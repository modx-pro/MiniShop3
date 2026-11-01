<?php

namespace MiniShop3\Processors\Gallery;

use MiniShop3\Services\Product\ProductDocumentPolicy;

/**
 * ACL документа для процессоров галереи (#922).
 *
 * Права msproductfile_* ничего не знают про Resource Group конкретного товара,
 * а процессоры работают либо через msProductData, либо по id файла — ни то, ни
 * другое не является modAccessibleObject, поэтому ACL не применяется сам.
 *
 * Отказ выглядит как «товар не найден»: 403 сам по себе подтверждает, что товар
 * существует, и так же ведут себя ядро MODX и защищённые маршруты product-data.
 */
trait GuardsProductDocumentTrait
{
    /**
     * Разрешена ли политика документа на этом товаре.
     */
    protected function productDocumentAllowed(
        int $productId,
        string $policy = ProductDocumentPolicy::POLICY_SAVE
    ): bool {
        return ProductDocumentPolicy::isAllowed($this->modx, $productId, $policy);
    }

    /**
     * То же, но товар берётся из файла галереи.
     *
     * @param object|null $file msProductFile
     */
    protected function fileDocumentAllowed(
        ?object $file,
        string $policy = ProductDocumentPolicy::POLICY_SAVE
    ): bool {
        if ($file === null || !method_exists($file, 'get')) {
            return false;
        }

        return $this->productDocumentAllowed((int) $file->get('product_id'), $policy);
    }

    /**
     * Единое сообщение об отказе для всех процессоров галереи.
     */
    protected function productDocumentDeniedMessage(): string
    {
        return (string) $this->modx->lexicon('ms3_gallery_err_no_product');
    }
}
