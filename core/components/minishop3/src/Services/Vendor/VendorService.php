<?php

namespace MiniShop3\Services\Vendor;

use MiniShop3\Model\msProductData;
use MiniShop3\Model\msVendor;
use MODX\Revolution\modX;

/**
 * Сервис для работы с производителями
 *
 * Обрабатывает бизнес-логику связанную с производителями товаров,
 * включая удаление и управление связями с товарами
 */
class VendorService
{
    /** @var modX */
    protected $modx;

    /**
     * @param modX $modx
     */
    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * Удаление производителя с обнулением связей в товарах
     *
     * При удалении производителя обнуляет поле vendor_id у всех товаров,
     * которые были связаны с этим производителем, чтобы избежать
     * битых связей в базе данных
     *
     * @param msVendor $vendor
     * @param array $ancestors
     * @return bool
     */
    public function removeVendor(msVendor $vendor, array $ancestors = []): bool
    {
        $vendorId = $vendor->get('id');

        // Обнуляем vendor_id у всех товаров этого производителя
        $query = $this->modx->newQuery(msProductData::class);
        $query->command('UPDATE');
        $query->set(['vendor_id' => 0]);
        $query->where(['vendor_id' => $vendorId]);

        if ($query->prepare() && $query->stmt->execute()) {
            // Логируем количество обновленных товаров
            $affectedRows = $query->stmt->rowCount();
            if ($affectedRows > 0) {
                $this->modx->log(
                    modX::LOG_LEVEL_INFO,
                    sprintf(
                        'VendorService: Обнулен vendor_id у %d товаров при удалении производителя ID=%d',
                        $affectedRows,
                        $vendorId
                    )
                );
            }
        }

        return true;
    }

    /**
     * Получить статистику по производителю
     *
     * Возвращает количество товаров, привязанных к производителю
     *
     * @param msVendor $vendor
     * @return array ['total_products' => int]
     */
    public function getVendorStatistics(msVendor $vendor): array
    {
        $vendorId = $vendor->get('id');

        $totalProducts = $this->modx->getCount(msProductData::class, [
            'vendor_id' => $vendorId
        ]);

        return [
            'total_products' => $totalProducts,
        ];
    }

    /**
     * Проверка возможности удаления производителя
     *
     * Проверяет, можно ли безопасно удалить производителя
     * Можно использовать для предупреждения пользователя
     *
     * @param msVendor $vendor
     * @return array ['can_remove' => bool, 'products_count' => int, 'warnings' => array]
     */
    public function canRemoveVendor(msVendor $vendor): array
    {
        $stats = $this->getVendorStatistics($vendor);
        $warnings = [];

        if ($stats['total_products'] > 0) {
            $warnings[] = sprintf(
                'У производителя "%s" есть %d товаров. При удалении производителя у них будет обнулен vendor_id.',
                $vendor->get('name'),
                $stats['total_products']
            );
        }

        return [
            'can_remove' => true, // Всегда можно удалить, но с предупреждениями
            'products_count' => $stats['total_products'],
            'warnings' => $warnings,
        ];
    }
}
