<?php

namespace MiniShop3\Services\Product;

use MiniShop3\Model\msProduct;
use MODX\Revolution\modX;

/**
 * ACL документа для операций над товаром по id (#922).
 *
 * `msProduct` наследует `modResource`, то есть `modAccessibleObject`, поэтому
 * `getObject()` сам отказывает по Resource Group — любой код, грузящий товар
 * как `msProduct`, защищён без единой явной проверки.
 *
 * А `msProductData` — обычный объект xPDO без ACL, и всё, что работает через
 * него или по сырым id (связи товаров, процессоры галереи), проходит мимо
 * прав на документ. Этот хелпер закрывает именно такие места: берёт id,
 * поднимает товар как ресурс и спрашивает политику.
 *
 * Отличие от {@see \MiniShop3\Services\Category\CategoryProductDocumentPolicy}:
 * тот работает с уже загруженным ресурсом в области категории, этот — с id.
 */
final class ProductDocumentPolicy
{
    public const POLICY_VIEW = 'view';
    public const POLICY_SAVE = 'save';

    /**
     * Товар как ресурс, если он существует и доступен текущему пользователю.
     *
     * null означает одно из двух, и различать их незачем: товара нет либо он
     * закрыт группой ресурсов. Наружу в обоих случаях уходит «не найден».
     *
     * Тип ослаблен до object, а политика спрашивается через method_exists —
     * так же, как в {@see \MiniShop3\Services\Category\CategoryProductDocumentPolicy}.
     * В рабочем коде getObject() вернёт msProduct либо null; свобода нужна,
     * чтобы проверку можно было прогнать на заглушке без MODX.
     *
     * @return msProduct|object|null
     */
    public static function load(modX $modx, int $productId): ?object
    {
        if ($productId <= 0) {
            return null;
        }

        $product = $modx->getObject(msProduct::class, $productId);

        return is_object($product) ? $product : null;
    }

    /**
     * Разрешена ли политика на этом товаре.
     */
    public static function isAllowed(modX $modx, int $productId, string $policy): bool
    {
        $product = self::load($modx, $productId);

        return $product !== null
            && method_exists($product, 'checkPolicy')
            && $product->checkPolicy($policy);
    }

    /**
     * Разрешена ли политика на всех перечисленных товарах.
     *
     * @param list<int> $productIds
     */
    public static function isAllowedAll(modX $modx, array $productIds, string $policy): bool
    {
        foreach (array_unique($productIds) as $productId) {
            if (!self::isAllowed($modx, (int) $productId, $policy)) {
                return false;
            }
        }

        return true;
    }
}
