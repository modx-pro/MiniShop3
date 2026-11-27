<?php

/**
 * #846: notification template UI must advertise a real chunk and the empty
 * template value must use the packaged default (tpl.msEmail), not disable email.
 *
 * Standalone (no MODX). Run: php tests/NotificationTemplateContractTest.php
 */

declare(strict_types=1);

$component = dirname(__DIR__);
$root = dirname($component, 3);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assertTrue = static function (bool $condition, string $case) use ($fail): void {
    if (!$condition) {
        $fail($case);
    }
};

$notification = (string) file_get_contents(
    $component . '/src/Notifications/Order/StatusChangedNotification.php'
);
$assertTrue(
    str_contains($notification, "\$config->get('template') ?: 'tpl.msEmail'"),
    'empty email template falls back to the packaged default chunk'
);
$assertTrue(
    !preg_match('/if\s*\(\s*empty\(\$template\)\s*\)\s*\{\s*return null;/', $notification),
    'empty template no longer disables email'
);

$channel = (string) file_get_contents($component . '/src/Notifications/Channels/EmailChannel.php');
$assertTrue(
    str_contains($channel, "Email notification chunk '{\$chunkName}' rendered empty or was not found"),
    'missing/empty chunk is logged instead of failing silently'
);

$chunks = (string) file_get_contents($root . '/_build/elements/chunks.php');
$assertTrue(str_contains($chunks, "'tpl.msEmail' => 'ms3_email'"), 'default email chunk is packaged');
$assertTrue(
    str_contains($chunks, "'tpl.msEmail.new.customer' => 'ms3_email_new_customer'"),
    'suggested email chunk is packaged'
);

foreach (['ru', 'en'] as $lang) {
    foreach (['notifications', 'vue'] as $topic) {
        $lexicon = (string) file_get_contents($component . "/lexicon/{$lang}/{$topic}.inc.php");
        $assertTrue(
            str_contains($lexicon, "\$_lang['ms3_notification_template_placeholder'] = 'tpl.msEmail.new.customer';"),
            "{$lang}/{$topic}: placeholder names a real chunk"
        );
        preg_match(
            '/\$_lang\[\'ms3_notification_template_hint\'\] = \'([^\']*)\';/',
            $lexicon,
            $hint
        );
        $assertTrue(
            isset($hint[1]) && str_contains($hint[1], 'tpl.msEmail'),
            "{$lang}/{$topic}: hint mentions the default chunk"
        );
        $assertTrue(
            !str_contains($lexicon, 'tpl.msEmail.order.new'),
            "{$lang}/{$topic}: nonexistent chunk is not advertised"
        );
    }
}

fwrite(STDOUT, "OK NotificationTemplateContractTest\n");
exit(0);
