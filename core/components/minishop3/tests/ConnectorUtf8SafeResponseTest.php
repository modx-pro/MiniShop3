<?php

/**
 * #689: legacy connector processors share UTF-8-safe JSON at one exit.
 *
 * Run: php tests/ConnectorUtf8SafeResponseTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL ConnectorUtf8SafeResponseTest: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $cond, string $case) use ($fail): void {
    if (!$cond) {
        $fail($case);
    }
};

$root = dirname(__DIR__);
$connector = dirname(__DIR__, 4) . '/assets/components/minishop3/connector.php';
$responseClass = $root . '/src/Router/Utf8SafeConnectorResponse.php';
$responseSrc = file_get_contents($root . '/src/Router/Response.php');
$traitSrc = file_get_contents($root . '/src/Processors/Api/ProcessesManagerConnectorRouteTrait.php');
$connectorSrc = file_get_contents($connector);
$classSrc = file_get_contents($responseClass);

$assertTrue(is_string($connectorSrc), 'connector.php missing');
$assertTrue(is_string($classSrc), 'Utf8SafeConnectorResponse.php missing');
$assertTrue(is_string($responseSrc), 'Response.php missing');
$assertTrue(is_string($traitSrc), 'ProcessesManagerConnectorRouteTrait.php missing');

$assertTrue(
    str_contains($connectorSrc, 'Utf8SafeConnectorResponse'),
    'connector.php must install Utf8SafeConnectorResponse'
);
$assertTrue(
    str_contains($classSrc, 'extends modConnectorResponse'),
    'Utf8SafeConnectorResponse must extend modConnectorResponse'
);
$assertTrue(
    str_contains($classSrc, 'encodeConnectorJson'),
    'Utf8SafeConnectorResponse must encode via Response::encodeConnectorJson'
);
$assertTrue(
    str_contains($responseSrc, 'function encodeConnectorJson'),
    'Response::encodeConnectorJson must exist as the single encode helper'
);
$assertTrue(
    str_contains($traitSrc, 'sanitizeUtf8ForJson'),
    'routed manager connector must keep sanitizeUtf8ForJson'
);

fwrite(STDOUT, "OK ConnectorUtf8SafeResponseTest\n");
exit(0);
