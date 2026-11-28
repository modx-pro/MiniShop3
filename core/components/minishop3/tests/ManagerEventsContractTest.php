<?php

/**
 * #847: manager customer/vendor events must follow the EventGate contract —
 * empty plugin output is not a veto, `false` is, and returnedValues can patch data.
 *
 * Run: php tests/ManagerEventsContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

foreach ([
    'src/Controllers/Api/Manager/CustomersController.php',
    'src/Controllers/Api/Manager/VendorsController.php',
] as $relative) {
    $source = (string) file_get_contents($root . '/core/components/minishop3/' . $relative);
    if (!str_contains($source, 'EventGate::normalizeMessage')) {
        $fail($relative . ' must normalize event output with EventGate (#847)');
    }
    if (!str_contains($source, 'EventGate::isCancelled')) {
        $fail($relative . ' must honor the false/cancel veto (#847)');
    }
    if (!str_contains($source, 'EventGate::getReturnedValues')) {
        $fail($relative . ' must support returnedValues (#847)');
    }
    if (str_contains($source, "implode(' ', \$response)")) {
        $fail($relative . ' must not treat multiple empty plugin outputs as a failure (#847)');
    }
    if (!str_contains($source, "array_merge(\$data, \$eventResponse['values'])")) {
        $fail($relative . ' must apply patched payload fields (#847)');
    }
}

$vendors = (string) file_get_contents($root . '/core/components/minishop3/src/Controllers/Api/Manager/VendorsController.php');
if (!str_contains($vendors, "'objects' => \$objects")) {
    $fail('Bulk delete events must include vendor objects (#847)');
}
if (preg_match('/msOn(Before)?VendorDelete[\s\S]{0,120}?\'mode\'/', $vendors)) {
    $fail('Delete events must not use update mode (#847)');
}

fwrite(STDOUT, "OK ManagerEventsContractTest (#847)\n");
exit(0);
