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

// --- TemplateTagGuard: общая маскировка открывателей -------------------------

$guard = $read($srcDir . '/Notifications/TemplateTagGuard.php');

foreach (['nonce', 'neutralize', 'restore', 'escapeHtml'] as $method) {
    if (!str_contains($guard, 'function ' . $method)) {
        $fail("TemplateTagGuard must define {$method}()");
    }
}

$noncePos = strpos($guard, 'function nonce');
if ($noncePos === false) {
    $fail('TemplateTagGuard::nonce() missing');
}
$nonceEnd = strpos($guard, 'function ', $noncePos + 10);
$nonceBody = substr($guard, $noncePos, $nonceEnd === false ? 400 : $nonceEnd - $noncePos);
if (preg_match('/bin2hex|bin2hex\(|[\'"][A-Za-z]/', $nonceBody)) {
    $fail('TemplateTagGuard::nonce() must build a digits-only marker (#888)');
}

// --- EmailChannel: нейтрализация вокруг рендера и парсера --------------------

$channel = $read($srcDir . '/Notifications/Channels/EmailChannel.php');

if (!str_contains($channel, 'function maskFlatPlaceholders')) {
    $fail('EmailChannel must define maskFlatPlaceholders()');
}

$posNeutralize = strpos($channel, '$this->tagGuard->neutralize(');
$posRender = strpos($channel, '$pdoFetch->getChunk($chunkName');
$posParser = strpos($channel, "processElementTags('', \$body");
$posRestore = strpos($channel, '$this->tagGuard->restore($body');

if ($posNeutralize === false || $posRender === false || $posParser === false || $posRestore === false) {
    $fail('EmailChannel::buildBody must neutralize, render, parse and restore');
}
if (!($posNeutralize < $posRender)) {
    $fail('EmailChannel must neutralize placeholders BEFORE rendering the chunk');
}
if (!($posParser < $posRestore)) {
    $fail('EmailChannel must restore tag openers AFTER the parser pass');
}

// Тема защищается тем же механизмом: getChunk() у pdoTools после Fenom сам
// подставляет [[+key]] и прогоняет остаток через разбор MODX-тегов, поэтому
// значение, вставленное через {$order_comment}, доезжало до парсера шаблоном.
$posSubjectMask = strpos($channel, '$this->maskFlatPlaceholders($subject');
$posSubjectNeutralize = strpos($channel, '$placeholders = $this->tagGuard->neutralize($placeholders, $nonce);', (int) $posSubjectMask);
$posSubjectRender = strpos($channel, "getChunk('@INLINE ' . \$subject");
$posSubjectRestore = strpos($channel, '$this->tagGuard->restore($subject');
$posSubjectExpand = strpos($channel, 'strtr($subject, $flatValues)');

foreach ([
    'maskFlatPlaceholders' => $posSubjectMask,
    'neutralizeTags' => $posSubjectNeutralize,
    'restoreTags' => $posSubjectRestore,
    'flat value expansion' => $posSubjectExpand,
] as $label => $position) {
    if ($position === false) {
        $fail("EmailChannel::buildSubject must use {$label} (#888)");
    }
}

if ($posSubjectRender !== false && !($posSubjectNeutralize < $posSubjectRender)) {
    $fail('EmailChannel::buildSubject must neutralize placeholders BEFORE rendering the subject');
}
// Разворачивание плоских значений идёт ПЕРЕД восстановлением открывающих
// последовательностей: маркер плоского ключа заканчивается меткой, и стоящая
// сразу за ним цифра из TAG_OPENERS складывалась бы с ней в то, что ищет
// restoreTags() — маркер рвался на теме вида «Заказ {num}1 шт».
if (!($posSubjectExpand < $posSubjectRestore)) {
    $fail('EmailChannel::buildSubject must expand flat values BEFORE restoring tag openers (#888)');
}

$posSubjectParser = strpos($channel, "processElementTags('', \$subject");
if ($posSubjectParser !== false && !($posSubjectParser < $posSubjectExpand)) {
    $fail('EmailChannel::buildSubject must run the parser BEFORE expanding flat values');
}

// --- StatusChangedNotification: подстановки в шаблон темы больше нет ---------

$notification = $read($srcDir . '/Notifications/Order/StatusChangedNotification.php');

if (str_contains($notification, 'processSubject')) {
    $fail('StatusChangedNotification must not substitute data into the subject template (#888)');
}

// --- Telegram/SMS: тот же guard вокруг getChunk, HTML только у Telegram -----

$renderPos = strpos($notification, 'function renderTemplate');
if ($renderPos === false) {
    $fail('StatusChangedNotification::renderTemplate() missing');
}
$renderEnd = strpos($notification, 'function ', $renderPos + 10);
$renderBody = substr(
    $notification,
    $renderPos,
    $renderEnd === false ? strlen($notification) - $renderPos : $renderEnd - $renderPos
);

$posRenderEscape = strpos($renderBody, '$guard->escapeHtml(');
$posRenderNeutralize = strpos($renderBody, '$guard->neutralize(');
$posRenderChunk = strpos($renderBody, 'getChunk($template');
$posRenderRestore = strpos($renderBody, '$guard->restore(');

foreach ([
    'escapeHtml' => $posRenderEscape,
    'neutralize' => $posRenderNeutralize,
    'getChunk' => $posRenderChunk,
    'restore' => $posRenderRestore,
] as $label => $position) {
    if ($position === false) {
        $fail("StatusChangedNotification::renderTemplate must use {$label} (#929)");
    }
}

if (!($posRenderEscape < $posRenderNeutralize && $posRenderNeutralize < $posRenderChunk && $posRenderChunk < $posRenderRestore)) {
    $fail('renderTemplate must escape, neutralize, render, then restore (#929)');
}
if (str_contains($renderBody, 'processElementTags')) {
    $fail('renderTemplate must not run the parser after restore (#929)');
}
if (!str_contains($renderBody, 'if ($escapeHtml)')) {
    $fail('renderTemplate must escape HTML only when asked (#929)');
}

$methodBody = static function (string $source, string $name) use ($fail): string {
    $pos = strpos($source, 'function ' . $name);
    if ($pos === false) {
        $fail("StatusChangedNotification::{$name}() missing");
    }
    $end = strpos($source, 'function ', $pos + 10);

    return substr(
        $source,
        $pos,
        $end === false ? strlen($source) - $pos : $end - $pos
    );
};

$telegramBody = $methodBody($notification, 'toTelegram');
$smsBody = $methodBody($notification, 'toSms');

if (!str_contains($telegramBody, "renderTemplate(\$config->get('template'), true)")) {
    $fail('toTelegram must render with HTML escaping (#929)');
}
if (str_contains($smsBody, 'renderTemplate(') && str_contains($smsBody, ', true')) {
    $fail('toSms must not HTML-escape placeholder values (#929)');
}
if (!str_contains($smsBody, "renderTemplate(\$config->get('template'))")) {
    $fail('toSms must render the configured template through renderTemplate (#929)');
}

// --- Utils::sendEmail: парсер по готовому телу ------------------------------

$utils = $read($srcDir . '/Utils/Utils.php');

$sendEmailPos = strpos($utils, 'function sendEmail(');
if ($sendEmailPos === false) {
    $fail('Utils::sendEmail() missing');
}
// Режем по следующему объявлению метода, а не по фиксированному окну: иначе
// сторож уедет за пределы метода, стоит ему подрасти.
$sendEmailEnd = strpos($utils, 'function ', $sendEmailPos + 10);
$sendEmailBody = substr(
    $utils,
    $sendEmailPos,
    $sendEmailEnd === false ? strlen($utils) - $sendEmailPos : $sendEmailEnd - $sendEmailPos
);
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
