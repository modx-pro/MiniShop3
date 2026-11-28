<?php

/**
 * #900: guest customers store telegram_chat_id on msCustomer itself. The
 * schema, generated xPDO map, public DTO and notification lookup must agree.
 *
 * Run: php tests/TelegramChatIdContractTest.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 4);
$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$read = static function (string $path) use ($root, $fail): string {
    if (!is_readable($root . $path)) {
        $fail("Missing {$path}");
    }

    return (string) file_get_contents($root . $path);
};

$schema = $read('/core/components/minishop3/schema/minishop3.mysql.schema.xml');
if (!str_contains($schema, '<field key="telegram_chat_id" dbtype="varchar" precision="255" phptype="string" null="true"/>')) {
    $fail('Schema must declare nullable telegram_chat_id (#900)');
}

$map = $read('/core/components/minishop3/src/Model/mysql/msCustomer.php');
if (!str_contains($map, "'telegram_chat_id' => null")) {
    $fail('Generated msCustomer map must include telegram_chat_id (#900)');
}
if (!preg_match("/'telegram_chat_id'\s*=>\s*\[[\s\S]*?'precision'\s*=>\s*'255'/", $map)) {
    $fail('Generated fieldMeta must describe telegram_chat_id (#900)');
}

$model = $read('/core/components/minishop3/src/Model/msCustomer.php');
if (!str_contains($model, '@property string|null $telegram_chat_id')) {
    $fail('msCustomer docblock must document telegram_chat_id (#900)');
}

$dto = $read('/core/components/minishop3/src/Services/Customer/CustomerPublicDto.php');
$publicBlockStart = strpos($dto, 'private const PUBLIC_FIELDS');
$publicBlockEnd = $publicBlockStart === false ? false : strpos($dto, '];', $publicBlockStart);
if ($publicBlockStart === false || $publicBlockEnd === false) {
    $fail('Cannot locate CustomerPublicDto::PUBLIC_FIELDS');
}
$publicBlock = substr($dto, $publicBlockStart, $publicBlockEnd - $publicBlockStart);
if (str_contains($publicBlock, 'telegram_chat_id')) {
    $fail('telegram_chat_id is internal PII and must not be in PUBLIC_FIELDS (#900)');
}
if (!str_contains($dto, 'telegram_chat_id') || !str_contains($dto, 'SYSTEM_NON_EDITABLE_FIELDS')) {
    $fail('telegram_chat_id must remain non-editable via Web API (#900)');
}

$service = $read('/core/components/minishop3/src/Services/Order/OrderStatusService.php');
if (!str_contains($service, "\$customer->get('telegram_chat_id')")) {
    $fail('OrderStatusService must read the native guest field (#900)');
}
if (!str_contains($service, "\$extended['telegram_chat_id']")) {
    $fail('Legacy extended.telegram_chat_id fallback must be kept (#900)');
}

$migration = $read('/core/components/minishop3/migrations/20261115120000_add_telegram_chat_id_to_customers.php');
if (!str_contains($migration, "'null' => true")) {
    $fail('Migration must create a nullable column (#900)');
}
if (substr($migration, -1) !== "\n") {
    $fail('Migration must end with a newline (#900)');
}

fwrite(STDOUT, "OK TelegramChatIdContractTest (#900)\n");
exit(0);
