<?php

/**
 * Regression for #844: msOnBeforeUpdateOrder / msOnUpdateOrder were registered
 * as MODX system events but never emitted. Order updates use the Save pair
 * with mode = modSystemEvent::MODE_UPD instead.
 *
 * Run: php tests/ObsoleteOrderUpdateEventsTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

// String literals only: docblocks may legitimately mention the retired names.
$stringLiterals = static function (string $source): array {
    $literals = [];
    foreach (token_get_all($source) as $token) {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $raw = $token[1];
        $quote = $raw[0] ?? '';
        if (($quote === "'" || $quote === '"') && str_ends_with($raw, $quote)) {
            $literals[] = substr($raw, 1, -1);
        }
    }

    return $literals;
};

$repoRoot = dirname(__DIR__, 4);
$retired = ['msOnBeforeUpdateOrder', 'msOnUpdateOrder'];

$eventsPath = $repoRoot . '/_build/elements/events.php';
if (!is_readable($eventsPath)) {
    $fail("Missing {$eventsPath}");
}
$eventsSource = file_get_contents($eventsPath);
$events = require $eventsPath;
if (!is_array($events)) {
    $fail('events.php must return an array of event names');
}

$eventLiterals = $stringLiterals($eventsSource);
foreach ($retired as $name) {
    if (in_array($name, $events, true) || in_array($name, $eventLiterals, true)) {
        $fail("events.php must not register the never-fired {$name} (#844)");
    }
}

foreach (['msOnBeforeSaveOrder', 'msOnSaveOrder'] as $name) {
    if (!in_array($name, $events, true)) {
        $fail("events.php must keep {$name} as the order update hook (#844)");
    }
}
if (!str_contains($eventsSource, 'MODE_UPD')) {
    $fail('events.php must document mode = modSystemEvent::MODE_UPD on the Save pair (#844)');
}

// No code may emit the retired names. Token-based literals keep similarly
// named msOn*UpdateOrderProduct events and docblock mentions out of the check.
$scan = static function (string $directory) use ($fail, $retired, $stringLiterals): void {
    if (!is_dir($directory)) {
        $fail("Missing {$directory}");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if ($source === false) {
            $fail("Cannot read {$file->getPathname()}");
        }
        $literals = $stringLiterals($source);
        foreach ($retired as $name) {
            if (in_array($name, $literals, true)) {
                $fail("{$file->getPathname()} still references {$name} (#844)");
            }
        }
    }
};
$scan($repoRoot . '/core/components/minishop3/src');

$orderModelPath = $repoRoot . '/core/components/minishop3/src/Model/msOrder.php';
$orderModel = file_get_contents($orderModelPath);
if ($orderModel === false) {
    $fail("Cannot read {$orderModelPath}");
}
foreach (['msOnBeforeSaveOrder', 'msOnSaveOrder'] as $name) {
    $pattern = "/invokeEvent\(\s*['\"]" . preg_quote($name, '/') . "['\"]\s*,\s*\[[^\]]*['\"]mode['\"]\s*=>/";
    if (!preg_match($pattern, $orderModel)) {
        $fail("msOrder::save() must fire {$name} with a mode payload (#844)");
    }
}
if (!str_contains($orderModel, 'modSystemEvent::MODE_UPD')) {
    $fail('msOrder::save() must use modSystemEvent::MODE_UPD for updates (#844)');
}

$resolverPath = $repoRoot . '/_build/resolvers/resolver_13_obsolete_events.php';
$resolverSource = is_readable($resolverPath) ? file_get_contents($resolverPath) : false;
if ($resolverSource === false) {
    $fail("Missing {$resolverPath}");
}
foreach ($retired as $name) {
    if (!in_array($name, $stringLiterals($resolverSource), true)) {
        $fail("Obsolete event resolver must clean {$name} (#844)");
    }
}
foreach (['modEvent::class', '->remove()', 'ACTION_UPGRADE', 'ACTION_INSTALL', 'groupname'] as $needle) {
    if (!str_contains($resolverSource, $needle)) {
        $fail("Obsolete event resolver must keep {$needle} wiring (#844)");
    }
}
if (!in_array('MiniShop3', $stringLiterals($resolverSource), true)) {
    $fail('Obsolete event resolver must keep the MiniShop3 ownership guard (#844)');
}

fwrite(STDOUT, "OK ObsoleteOrderUpdateEventsTest (#844)\n");
exit(0);
