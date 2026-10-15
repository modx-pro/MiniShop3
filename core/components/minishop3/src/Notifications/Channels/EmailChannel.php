<?php

namespace MiniShop3\Notifications\Channels;

use MODX\Revolution\modX;
use MODX\Revolution\Mail\modMail;
use MODX\Revolution\Mail\modPHPMailer;
use ModxPro\PdoTools\Fetch;
use MiniShop3\Model\msOrder;
use MiniShop3\Notifications\ChannelInterface;
use MiniShop3\Notifications\Notification;
use MiniShop3\Notifications\Messages\EmailMessage;

/**
 * Email notification channel
 *
 * Built-in channel for sending email notifications via MODX PHPMailer
 */
class EmailChannel implements ChannelInterface
{
    /**
     * Открывающие последовательности тегов и метки для их подмены (#888).
     *
     * Fenom подставляет значения плейсхолдеров в вывод как есть, а идущий
     * следом парсер читает готовое тело уже как шаблон. С установленным
     * pdoTools `$modx->getParser()` — это Fenom, а не разбор MODX-тегов,
     * поэтому из комментария покупателя исполнялись и `[[++setting]]`, и
     * `{$_modx->runSnippet(...)}`.
     *
     * Данные уходят в тело с подменёнными открывающими последовательностями и
     * возвращаются к исходному виду уже после парсера: чанк по-прежнему может
     * пользоваться и MODX-тегами, и Fenom, а текст покупателя остаётся
     * текстом.
     */
    private const TAG_OPENERS = [
        '[[' => 'MODX',
        '{' => 'FENOM',
    ];

    protected modX $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    /**
     * @inheritDoc
     */
    public function send(Notification $notification, array $recipient, msOrder $order): bool
    {
        // Determine recipient type from context
        $recipientType = $recipient['type'] ?? 'customer';

        // Get message from notification
        $message = $notification->toEmail($recipientType);

        if (!$message instanceof EmailMessage) {
            return false;
        }

        // Get recipient email
        $email = $this->getRecipientEmail($recipient);

        if (empty($email)) {
            return false;
        }

        // Build email body
        $body = $this->buildBody($message, $notification);
        $subject = $this->buildSubject($message, $notification);

        // Send email
        return $this->sendEmail($email, $subject, $body, $message);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'email';
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        // Email is always available if MODX mail is configured
        $emailSender = $this->modx->getOption('emailsender');
        return !empty($emailSender);
    }

    /**
     * @inheritDoc
     */
    public function getRequirements(): array
    {
        return ['emailsender'];
    }

    /**
     * Get email address from recipient data
     *
     * @param array $recipient
     * @return string|null
     */
    protected function getRecipientEmail(array $recipient): ?string
    {
        // Direct email field
        if (!empty($recipient['email'])) {
            return $recipient['email'];
        }

        // Customer object
        if (!empty($recipient['customer']) && is_array($recipient['customer'])) {
            return $recipient['customer']['email'] ?? null;
        }

        // Address email (fallback, same order as SmsChannel phone)
        if (!empty($recipient['address']['email'])) {
            return $recipient['address']['email'];
        }

        return null;
    }

    /**
     * Build email body from message
     *
     * @param EmailMessage $message
     * @param Notification $notification
     * @return string
     */
    protected function buildBody(EmailMessage $message, Notification $notification): string
    {
        // Load MiniShop3 lexicon topics for template rendering
        $this->modx->lexicon->load('minishop3:default');
        $this->modx->lexicon->load('minishop3:cart');

        $body = $message->getBody();
        $nonce = null;

        // If chunk is specified, render it
        if ($chunkName = $message->getChunk()) {
            $placeholders = array_merge(
                $notification->getPlaceholders(),
                $message->getPlaceholders()
            );

            // Данные покупателя не должны дожить до парсера в виде тегов.
            $nonce = $this->tagNonce();
            $placeholders = $this->neutralizeTags($placeholders, $nonce);

            // Use output buffering to capture any stray output from Fenom/pdoTools
            ob_start();

            // Use pdoTools for Fenom rendering
            if ($this->modx->services->has(Fetch::class)) {
                /** @var Fetch $pdoFetch */
                $pdoFetch = $this->modx->services->get(Fetch::class);
                $body = $pdoFetch->getChunk($chunkName, $placeholders);
            } else {
                $body = $this->modx->getChunk($chunkName, $placeholders);
            }

            // Discard any stray output
            ob_end_clean();
        }

        // Process MODX tags
        $this->modx->getParser()->processElementTags('', $body, true, false, '[[', ']]', [], 10);
        $this->modx->getParser()->processElementTags('', $body, true, true, '[[', ']]', [], 10);

        return $nonce === null ? $body : $this->restoreTags($body, $nonce);
    }

    /**
     * Build email subject from message
     *
     * Supports both Fenom syntax ({$var}, {'key' | lexicon})
     * and MODX syntax ([[+var]], [[%lexicon_key]])
     *
     * @param EmailMessage $message
     * @param Notification $notification
     * @return string
     */
    protected function buildSubject(EmailMessage $message, Notification $notification): string
    {
        $subject = $message->getSubject();

        // Merge placeholders
        $placeholders = array_merge(
            $notification->getPlaceholders(),
            $message->getPlaceholders()
        );

        // Use output buffering to capture any stray output from Fenom/pdoTools
        ob_start();

        // Use pdoTools Fenom parser for subject (supports Fenom and MODX syntax)
        if ($this->modx->services->has(Fetch::class)) {
            /** @var Fetch $pdoFetch */
            $pdoFetch = $this->modx->services->get(Fetch::class);
            $subject = $pdoFetch->getChunk('@INLINE ' . $subject, $placeholders);
        } else {
            // Process MODX tags (lexicon, settings, etc.) — до подстановки данных.
            $this->modx->getParser()->processElementTags('', $subject, true, false, '[[', ']]', [], 10);
            $this->modx->getParser()->processElementTags('', $subject, true, true, '[[', ']]', [], 10);
        }

        // Discard any stray output
        ob_end_clean();

        // Плоский {key} — последним шагом, когда парсеров за ним уже нет.
        // Fenom понимает только {$key}, поэтому темы вида «Заказ {num}»
        // держатся на этой подстановке. Раньше её делал
        // StatusChangedNotification::processSubject() до компиляции, и
        // комментарий покупателя попадал в текст шаблона (#888).
        return $this->fillFlatPlaceholders($subject, $placeholders);
    }

    /**
     * Подставить плоские плейсхолдеры вида {key}.
     *
     * Только скаляры и только точное совпадение {key}: без фигурных скобок
     * подстановка затронула бы любое вхождение имени ключа в тексте.
     *
     * @param array<string, mixed> $placeholders
     */
    protected function fillFlatPlaceholders(string $subject, array $placeholders): string
    {
        if (!str_contains($subject, '{')) {
            return $subject;
        }

        $search = [];
        $replace = [];

        foreach ($placeholders as $key => $value) {
            if (is_scalar($value)) {
                $search[] = '{' . $key . '}';
                $replace[] = (string) $value;
            }
        }

        return $search === [] ? $subject : str_replace($search, $replace, $subject);
    }

    /**
     * Разовая метка для подмены открывающих последовательностей тегов.
     *
     * Случайная на каждое письмо: иначе покупатель мог бы вписать саму метку
     * в комментарий и получить обратно `[[` или `{` после разворачивания.
     */
    protected function tagNonce(): string
    {
        return 'ms3tag' . bin2hex(random_bytes(8));
    }

    /**
     * Подменить открывающие последовательности тегов во всех строках данных.
     *
     * Рекурсивно, потому что плейсхолдеры письма — вложенные массивы (order,
     * address, customer, products). Объекты не обходим: в плейсхолдеры письма
     * они не попадают, всё приводится к массивам через toArray().
     */
    protected function neutralizeTags(mixed $value, string $nonce): mixed
    {
        if (is_array($value)) {
            return array_map(fn($item): mixed => $this->neutralizeTags($item, $nonce), $value);
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $search = [];
        $replace = [];
        foreach (self::TAG_OPENERS as $opener => $label) {
            $search[] = $opener;
            $replace[] = $nonce . $label;
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * Вернуть подменённые последовательности на место — после всех парсеров.
     */
    protected function restoreTags(string $text, string $nonce): string
    {
        $search = [];
        $replace = [];
        foreach (self::TAG_OPENERS as $opener => $label) {
            $search[] = $nonce . $label;
            $replace[] = $opener;
        }

        return str_replace($search, $replace, $text);
    }

    /**
     * Send email via MODX PHPMailer
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     * @param EmailMessage $message
     * @return bool
     */
    protected function sendEmail(string $email, string $subject, string $body, EmailMessage $message): bool
    {
        try {
            $mail = new modPHPMailer($this->modx);
            $mail->setHTML(true);

            $mail->address('to', trim($email));
            $mail->set(modMail::MAIL_SUBJECT, trim($subject));
            $mail->set(modMail::MAIL_BODY, $body);

            // Sender
            $from = $message->getFrom() ?? $this->modx->getOption('emailsender');
            $fromName = $message->getFromName() ?? $this->modx->getOption('site_name');
            $mail->set(modMail::MAIL_FROM, $from);
            $mail->set(modMail::MAIL_FROM_NAME, $fromName);

            // Reply-To
            if ($replyTo = $message->getReplyTo()) {
                $mail->address('reply-to', $replyTo);
            }

            // Attachments
            foreach ($message->getAttachments() as $attachment) {
                if (file_exists($attachment['path'])) {
                    $mail->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            $result = $mail->send();

            if (!$result) {
                $errorInfo = $mail->mailer->ErrorInfo ?? 'Unknown error';
                $this->modx->log(modX::LOG_LEVEL_ERROR, "[ms3] Email send failed: " . $errorInfo);
            }

            $mail->reset();

            return $result;
        } catch (\Throwable $e) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                "[ms3] Email exception: " . $e->getMessage()
            );
            return false;
        }
    }
}
