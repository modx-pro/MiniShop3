<?php

/**
 * @var \MODX\Revolution\modX $modx
 * @var array $namespace
 */

// Load the classes
$modx->addPackage('MiniShop3\Model', $namespace['path'] . 'src/', null, 'MiniShop3\\');

$modx->services->add('ms3', function ($c) use ($modx) {
    return new MiniShop3\MiniShop3($modx);
});
