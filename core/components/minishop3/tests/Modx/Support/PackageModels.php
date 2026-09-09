<?php

declare(strict_types=1);

namespace MiniShop3\Tests\Modx\Support;

use MiniShop3\Model\msCategoryMember;
use MiniShop3\Model\msCategoryOption;
use MiniShop3\Model\msCustomer;
use MiniShop3\Model\msCustomerAddress;
use MiniShop3\Model\msCustomerToken;
use MiniShop3\Model\msDelivery;
use MiniShop3\Model\msDeliveryMember;
use MiniShop3\Model\msExtraField;
use MiniShop3\Model\msGridField;
use MiniShop3\Model\msLink;
use MiniShop3\Model\msModelField;
use MiniShop3\Model\msModelFieldSection;
use MiniShop3\Model\msNotificationConfig;
use MiniShop3\Model\msOption;
use MiniShop3\Model\msOptionGroup;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderAddress;
use MiniShop3\Model\msOrderLog;
use MiniShop3\Model\msOrderProduct;
use MiniShop3\Model\msOrderStatus;
use MiniShop3\Model\msPageSection;
use MiniShop3\Model\msPayment;
use MiniShop3\Model\msProductData;
use MiniShop3\Model\msProductField;
use MiniShop3\Model\msProductFile;
use MiniShop3\Model\msProductLink;
use MiniShop3\Model\msProductOption;
use MiniShop3\Model\msVendor;

/**
 * xPDO table classes for live testbench (not modResource subclasses).
 */
final class PackageModels
{
    /**
     * @return list<class-string>
     */
    public static function tables(): array
    {
        return [
            msCategoryMember::class,
            msCategoryOption::class,
            msCustomer::class,
            msCustomerAddress::class,
            msCustomerToken::class,
            msDelivery::class,
            msDeliveryMember::class,
            msExtraField::class,
            msGridField::class,
            msLink::class,
            msModelField::class,
            msModelFieldSection::class,
            msNotificationConfig::class,
            msOption::class,
            msOptionGroup::class,
            msOrder::class,
            msOrderAddress::class,
            msOrderLog::class,
            msOrderProduct::class,
            msOrderStatus::class,
            msPageSection::class,
            msPayment::class,
            msProductData::class,
            msProductField::class,
            msProductFile::class,
            msProductLink::class,
            msProductOption::class,
            msVendor::class,
        ];
    }
}
