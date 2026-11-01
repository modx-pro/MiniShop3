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
     *
     * product_id = 0 означает, что документа нет: так выглядят файлы-сироты
     * и выборка GetList по product_id = 0 — её запрос фильтрует ровно
     * `product_id = 0` и ничего чужого не отдаёт. Проверять там нечего,
     * доступ остаётся ограничен правом компонента и фильтром запроса.
     *
     * Подменить это из запроса нельзя: у операций по файлу product_id
     * читается из строки файла, а не из параметров.
     */
    protected function productDocumentAllowed(
        int $productId,
        string $policy = ProductDocumentPolicy::POLICY_SAVE
    ): bool {
        if ($productId === 0) {
            return true;
        }

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
