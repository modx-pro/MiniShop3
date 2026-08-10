<?php

/**
 * Static checks for KeyValueFieldService decode/normalize/validate (without MODX).
 *
 * Run: php tests/KeyValueFieldServiceTest.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MiniShop3\Services\ExtraFields\KeyValueFieldService;

if (!class_exists(\MODX\Revolution\modX::class, false)) {
    eval(<<<'PHP'
namespace MODX\Revolution {
    class modX
    {
        public const LOG_LEVEL_WARN = 2;

        public function log($level, $message): void
        {
        }
    }
}
PHP);
}

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$assertSame = static function ($expected, $actual, string $case) use ($fail): void {
    if ($actual !== $expected) {
        $fail($case . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

$assertThrows = static function (callable $callback, string $needle, string $case) use ($fail): void {
    try {
        $callback();
        $fail($case . ': expected exception');
    } catch (\InvalidArgumentException $e) {
        if (!str_contains($e->getMessage(), $needle)) {
            $fail($case . ': unexpected message ' . $e->getMessage());
        }
    }
};

$service = new KeyValueFieldService(new \MODX\Revolution\modX());

$assertTrue($service->isKeyValueXtype(KeyValueFieldService::XTYPE), 'xtype match');
$assertTrue(!$service->isKeyValueXtype('ms3-repeater'), 'xtype mismatch');

$defaults = $service->defaultConfig();
$assertSame('fixed', $defaults['mode'], 'default mode is fixed');
$assertSame([], $defaults['keys'], 'default keys empty');

$parsed = $service->parseConfig(['mode' => 'free', 'keys' => [['key' => ' a ', 'valueType' => 'number', 'required' => 1]]]);
$assertSame('free', $parsed['mode'], 'parse keeps free mode');
$assertSame('a', $parsed['keys'][0]['key'], 'parse trims key');
$assertSame('number', $parsed['keys'][0]['valueType'], 'parse valueType number');
$assertTrue($parsed['keys'][0]['required'] === true, 'parse required bool');

$fixedEmpty = $service->validateConfigSchema(['mode' => 'fixed', 'keys' => []]);
$assertTrue($fixedEmpty['success'] === false, 'fixed mode requires keys');

$fixedOk = $service->validateConfigSchema([
    'mode' => 'fixed',
    'keys' => [
        ['key' => 'calories', 'label' => 'Calories', 'valueType' => 'string', 'required' => true],
        ['key' => 'protein', 'label' => 'Protein', 'valueType' => 'number', 'required' => false],
    ],
]);
$assertTrue($fixedOk['success'] === true, 'fixed schema valid');

$dup = $service->validateConfigSchema([
    'mode' => 'fixed',
    'keys' => [
        ['key' => 'calories'],
        ['key' => 'calories'],
    ],
]);
$assertTrue($dup['success'] === false, 'duplicate schema keys rejected');

$assertSame(
    ['calories' => '250', 'protein' => 12],
    $service->decodeValue('{"calories":"250","protein":12}'),
    'decode JSON object'
);

$assertThrows(
    static fn () => $service->decodeValue('["a","b"]'),
    'JSON object',
    'decode rejects JSON list'
);

$assertThrows(
    static fn () => $service->decodeValue(['a', 'b']),
    'object map',
    'decode rejects PHP list'
);

$nutritionConfig = [
    'mode' => 'fixed',
    'keys' => [
        ['key' => 'calories', 'label' => 'Calories', 'valueType' => 'string', 'required' => true],
        ['key' => 'protein', 'label' => 'Protein', 'valueType' => 'number', 'required' => false],
    ],
];

$normalized = $service->normalizeMap(
    ['calories' => ' 250 ', 'protein' => '12', 'extra' => 'drop-me'],
    $nutritionConfig
);
$assertSame(
    ['calories' => '250', 'protein' => 12],
    $normalized,
    'normalize fixed keeps schema keys only'
);

$valid = $service->validateMap($normalized, $nutritionConfig);
$assertTrue(($valid['ok'] ?? false) === true, 'validate nutrition map ok');

$missingRequired = $service->validateMap(['calories' => '', 'protein' => null], $nutritionConfig);
$assertTrue(($missingRequired['ok'] ?? true) === false, 'required key fails');

// validateMap runs after normalizeMap; unknown keys are already stripped (strip-first).
$unknownKey = $service->validateMap(['calories' => '1', 'weird' => 'x'], $nutritionConfig);
$assertTrue(($unknownKey['ok'] ?? false) === true, 'validateMap ignores unknown keys after strip-first');

$freeConfig = ['mode' => 'free', 'keys' => []];
$freeNormalized = $service->normalizeMap(['' => 'skip', ' meta ' => ' value '], $freeConfig);
$assertSame(['meta' => 'value'], $freeNormalized, 'normalize free trims and drops empty keys');

$processed = $service->processValue(
    ['calories' => '250', 'protein' => '8.5'],
    $nutritionConfig
);
$assertSame(
    ['calories' => '250', 'protein' => 8.5],
    $processed,
    'processValue end-to-end'
);

$assertThrows(
    static fn () => $service->processValue(['protein' => 1], $nutritionConfig),
    'required',
    'processValue fails on missing required'
);

// Fixed mode strips unknown keys before validate; processValue must not reject them.
$assertSame(
    ['calories' => '1', 'protein' => ''],
    $service->processValue(
        ['calories' => '1', 'protein' => '', 'extra' => 'ignored'],
        $nutritionConfig
    ),
    'processValue strips unknown keys in fixed mode'
);

$assertSame(
    1000,
    $service->processValue(
        ['calories' => '1', 'protein' => '1e3'],
        $nutritionConfig
    )['protein'],
    'processValue casts exponential numeric strings'
);

$encoded = $service->encodeConfig($nutritionConfig);
$assertTrue(is_string($encoded) && $encoded !== '', 'encodeConfig returns JSON string');

fwrite(STDOUT, "OK KeyValueFieldServiceTest\n");
exit(0);
