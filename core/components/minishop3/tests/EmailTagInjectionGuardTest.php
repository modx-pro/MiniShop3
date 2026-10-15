<?php

/**
 * Regression #888: порядок сборки письма и экранирование данных покупателя.
 *
 * Проверка статическая — она сторожит именно порядок операций, который
 * поведенческим тестом без MODX и pdoTools не ухватить:
 *
 *   - данные нейтрализуются ДО рендера и возвращаются ПОСЛЕ парсера;
 *   - плоские {key} подставляются последними, а не до компиляции;
 *   - по уже собранному телу парсер больше не ходит там, где тело пришло
 *     снаружи;
 *   - поля покупателя в штатных чанках выводятся экранированными.
 *
 * Запуск: php tests/EmailTagInjectionGuardTest.php
 */

declare(strict_types=1);

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$srcDir = dirname(__DIR__) . '/src';
$chunksDir = dirname(__DIR__) . '/elements/chunks';

$read = static function (string $path) use ($fail): string {
    if (!is_readable($path)) {
        $fail('missing ' . $path);
    }
    $content = file_get_contents($path);
    if ($content === false) {
        $fail('cannot read ' . $path);
    }

    return $content;
};

// --- EmailChannel: нейтрализация вокруг рендера и парсера --------------------

$channel = $read($srcDir . '/Notifications/Channels/EmailChannel.php');

foreach (['neutralizeTags', 'restoreTags', 'tagNonce', 'fillFlatPlaceholders'] as $method) {
    if (!str_contains($channel, 'function ' . $method)) {
        $fail("EmailChannel must define {$method}()");
    }
}

$posNeutralize = strpos($channel, '$this->neutralizeTags(');
$posRender = strpos($channel, '$pdoFetch->getChunk($chunkName');
$posParser = strpos($channel, "processElementTags('', \$body");
$posRestore = strpos($channel, '$this->restoreTags($body');

if ($posNeutralize === false || $posRender === false || $posParser === false || $posRestore === false) {
    $fail('EmailChannel::buildBody must neutralize, render, parse and restore');
}
if (!($posNeutralize < $posRender)) {
    $fail('EmailChannel must neutralize placeholders BEFORE rendering the chunk');
}
if (!($posParser < $posRestore)) {
    $fail('EmailChannel must restore tag openers AFTER the parser pass');
}

// Тема: плоская подстановка последней, иначе значение снова станет шаблоном.
$posSubjectParser = strpos($channel, "processElementTags('', \$subject");
$posSubjectFill = strpos($channel, '$this->fillFlatPlaceholders($subject');
if ($posSubjectFill === false) {
    $fail('EmailChannel::buildSubject must fill flat placeholders');
}
if ($posSubjectParser !== false && !($posSubjectParser < $posSubjectFill)) {
    $fail('EmailChannel::buildSubject must run the parser BEFORE filling placeholders');
}

// --- StatusChangedNotification: подстановки в шаблон темы больше нет ---------

$notification = $read($srcDir . '/Notifications/Order/StatusChangedNotification.php');

if (str_contains($notification, 'processSubject')) {
    $fail('StatusChangedNotification must not substitute data into the subject template (#888)');
}

// --- Utils::sendEmail: парсер по готовому телу ------------------------------

$utils = $read($srcDir . '/Utils/Utils.php');

$sendEmailPos = strpos($utils, 'function sendEmail(');
if ($sendEmailPos === false) {
    $fail('Utils::sendEmail() missing');
}
$sendEmailBody = substr($utils, $sendEmailPos, 1200);
if (str_contains($sendEmailBody, 'processElementTags')) {
    $fail('Utils::sendEmail() must not run the parser over a body it received already built (#888)');
}

// --- Чанки писем: поля покупателя экранируются ------------------------------

/**
 * Выражения, через которые в письмо попадают данные покупателя или снимок
 * заказа. Каждое должно выводиться с модификатором экранирования.
 */
$mustEscape = [
    'ms3_email.tpl' => [
        '$product.name',
        '$product.pagetitle',
        '$product.options',
    ],
    'ms3_email_new_manager.tpl' => [
        '$address[$field]',
        '$address.comment',
    ],
];

foreach ($mustEscape as $file => $expressions) {
    $content = $read($chunksDir . '/' . $file);

    foreach ($expressions as $expression) {
        $offset = 0;
        $found = false;

        while (($pos = strpos($content, '{' . $expression, $offset)) !== false) {
            $found = true;
            $end = strpos($content, '}', $pos);
            if ($end === false) {
                $fail("{$file}: unterminated output for {$expression}");
            }
            $output = substr($content, $pos, $end - $pos + 1);

            if (!preg_match('/\|\s*(e|escape)\s*[}:]/', $output)) {
                $fail("{$file}: {$output} must be escaped with |e (#888)");
            }

            $offset = $end;
        }

        if (!$found) {
            $fail("{$file}: expected an output of {$expression} to guard");
        }
    }
}

fwrite(STDOUT, "OK EmailTagInjectionGuardTest\n");
exit(0);
