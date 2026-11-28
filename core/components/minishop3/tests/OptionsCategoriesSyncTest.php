<?php

/**
 * Option category sync must detach for the modern list payload (#869).
 *
 * The flat list payload ([id, id, ...]) is the desired set of enabled category
 * ids: an option saved with a shorter list must lose the previously linked
 * categories that are absent from it. The old implementation only added the
 * listed ids and never removed anything, so unchecking a category kept the link.
 *
 * Run: php tests/OptionsCategoriesSyncTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL OptionsCategoriesSyncTest: {$message}\n");
    exit(1);
};

$controllerPath = dirname(__DIR__, 4) . '/core/components/minishop3/src/Controllers/Api/Manager/OptionsController.php';
if (!is_readable($controllerPath)) {
    $fail('Cannot read ' . $controllerPath);
}

$source = (string)file_get_contents($controllerPath);

if (!str_contains($source, 'if ($this->isCategoriesIdListPayload($payload))')) {
    $fail('sync must branch on the flat list payload shape (#869)');
}
if (!str_contains($source, 'array_diff($current, $enabled)')) {
    $fail('list sync must compute detach as current links minus enabled ids (#869)');
}
if (!str_contains($source, 'removeFromCategories($optionId, $detach)')) {
    $fail('list sync must call removeFromCategories with the detach set (#869)');
}
// Exercise the shape detector itself: invalid scalars must not be mistaken for
// an empty list (which would detach every category) (#869 review).
require dirname(__DIR__) . '/vendor/autoload.php';
$method = new \ReflectionMethod(
    \MiniShop3\Controllers\Api\Manager\OptionsController::class,
    'isCategoriesIdListPayload'
);
$controller = (new \ReflectionClass(\MiniShop3\Controllers\Api\Manager\OptionsController::class))
    ->newInstanceWithoutConstructor();
$cases = [
    [[], true],
    [[3, 5], true],
    ['[3,5]', true],
    [['3' => true], false],
    ['{}', false],
    ['invalid-json', false],
    [5, false],
    [null, false],
];
foreach ($cases as [$payload, $expected]) {
    if ($method->invoke($controller, $payload) !== $expected) {
        $fail('unexpected payload classification for ' . var_export($payload, true));
    }
}

fwrite(STDOUT, "OK OptionsCategoriesSyncTest (#869)\n");
exit(0);
