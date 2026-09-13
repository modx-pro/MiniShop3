<?php

declare(strict_types=1);

/**
 * Leftover files removed from the Extra that MODX upgrade will not delete (#704).
 *
 * ACTION_UPGRADE copies the new tree via copyTree() and never unlinks files that
 * left the package. Connector still autoloads leftover processors from disk.
 *
 * When you delete a PHP file from the shipped component, add its path here
 * (relative to core/components/minishop3/ or assets/components/minishop3/).
 * Missing files on a site are skipped, not an error.
 *
 * Product/Autocomplete.php is listed while still in the tree (#690 will remove
 * the source). Install/upgrade then purge it so #688 SQLi cannot linger on disk.
 *
 * @return array{core: list<string>, assets: list<string>}
 */
return [
    'core' => [
        'src/Processors/Category/GetList.php',
        'src/Processors/Category/Option/Activate.php',
        'src/Processors/Category/Option/Add.php',
        'src/Processors/Category/Option/Deactivate.php',
        'src/Processors/Category/Option/Duplicate.php',
        'src/Processors/Category/Option/GetList.php',
        'src/Processors/Category/Option/Multiple.php',
        'src/Processors/Category/Option/Remove.php',
        'src/Processors/Category/Option/Required.php',
        'src/Processors/Category/Option/Unrequired.php',
        'src/Processors/Category/Option/Update.php',
        'src/Processors/Category/Option/UpdateFromGrid.php',
        'src/Processors/Config/Read.php',
        'src/Processors/Gallery/RemoveCatalogs.php',
        'src/Processors/Order/Create.php',
        'src/Processors/Order/Get.php',
        'src/Processors/Order/GetList.php',
        'src/Processors/Order/GetLog.php',
        'src/Processors/Order/Multiple.php',
        'src/Processors/Order/Product/Create.php',
        'src/Processors/Order/Product/Get.php',
        'src/Processors/Order/Product/GetList.php',
        'src/Processors/Order/Product/Remove.php',
        'src/Processors/Order/Product/Update.php',
        'src/Processors/Order/Remove.php',
        'src/Processors/Order/ToggleDraft.php',
        'src/Processors/Order/Update.php',
        'src/Processors/Product/Autocomplete.php',
        'src/Processors/Product/ProductLink/GetList.php',
        'src/Processors/Product/ProductLink/Multiple.php',
        'src/Processors/Settings/Delivery/Payments/Disable.php',
        'src/Processors/Settings/Delivery/Payments/Enable.php',
        'src/Processors/Settings/Delivery/Payments/GetList.php',
        'src/Processors/Settings/Delivery/Payments/Multiple.php',
        'src/Processors/Settings/Option/Assign.php',
        'src/Processors/Settings/Option/Create.php',
        'src/Processors/Settings/Option/Duplicate.php',
        'src/Processors/Settings/Option/Get.php',
        'src/Processors/Settings/Option/GetCategories.php',
        'src/Processors/Settings/Option/GetList.php',
        'src/Processors/Settings/Option/GetNodes.php',
        'src/Processors/Settings/Option/GetTypes.php',
        'src/Processors/Settings/Option/Multiple.php',
        'src/Processors/Settings/Option/Remove.php',
        'src/Processors/Settings/Option/Update.php',
        'src/Processors/Settings/Payment/Deliveries/Disable.php',
        'src/Processors/Settings/Payment/Deliveries/Enable.php',
        'src/Processors/Settings/Payment/Deliveries/GetList.php',
        'src/Processors/Settings/Payment/Deliveries/Multiple.php',
        'src/Processors/Utilities/ExtraField/Create.php',
        'src/Processors/Utilities/ExtraField/Get.php',
        'src/Processors/Utilities/ExtraField/GetClassNodes.php',
        'src/Processors/Utilities/ExtraField/GetList.php',
        'src/Processors/Utilities/ExtraField/Multiple.php',
        'src/Processors/Utilities/ExtraField/Remove.php',
        'src/Processors/Utilities/ExtraField/Update.php',
    ],
    'assets' => [],
];
