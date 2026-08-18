<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap: Composer autoload + minimal xPDO stubs for model subclasses.
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/support/xpdo_stub.php';
require __DIR__ . '/support/xpdo_om_stub.php';
require __DIR__ . '/support/modresource_stub.php';

// tests/support/ is lowercase on disk; PSR-4 expects Support/ — require on Linux CI (#595).
require __DIR__ . '/support/SqliteDraftCartProduct.php';
require __DIR__ . '/support/SqliteHarnessCart.php';
require __DIR__ . '/support/SqliteDraftCartHarnessTrait.php';
