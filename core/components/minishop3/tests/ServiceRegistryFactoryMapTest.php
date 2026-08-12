<?php

/**
 * Smoke: ServiceRegistry uses factory map, not switch/in_array wiring (#345).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\ServiceRegistry;
use MiniShop3\ServiceRegistryFactories;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ServiceRegistryFactoryMapTest: {$message}\n");
    exit(1);
};

$registrySrc = file_get_contents(__DIR__ . '/../src/ServiceRegistry.php');
if ($registrySrc === false) {
    $fail('ServiceRegistry.php unreadable');
}

if (preg_match('/switch\s*\(\s*\$serviceKey\s*\)/', $registrySrc)) {
    $fail('ServiceRegistry must not switch on serviceKey for wiring');
}

if (preg_match('/controllersWithMs3Only|servicesWithModxAndMs3|servicesWithDependencies/', $registrySrc)) {
    $fail('ServiceRegistry must not use in_array wiring lists');
}

$reflection = new ReflectionClass(ServiceRegistry::class);
$registry = $reflection->newInstanceWithoutConstructor();
$defaultServices = $reflection->getProperty('defaultServices');
$defaultServices->setAccessible(true);
$services = $defaultServices->getValue($registry);

$factories = ServiceRegistryFactories::map();
foreach (array_keys($services) as $key) {
    if (!isset($factories[$key])) {
        $fail("Missing factory for default service key: {$key}");
    }
}

$orphanFactories = array_diff(array_keys($factories), array_keys($services));
if ($orphanFactories !== []) {
    $fail('Factory map has keys not in defaultServices: ' . implode(', ', $orphanFactories));
}

echo "OK ServiceRegistryFactoryMapTest (" . count($factories) . " factories)\n";
