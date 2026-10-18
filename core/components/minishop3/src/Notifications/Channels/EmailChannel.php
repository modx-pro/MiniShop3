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
use MiniShop3\Notifications\TemplateTagGuard;

/**
 * Email notification channel
 *
 * Built-in channel for sending email notifications via MODX PHPMailer
 */
class EmailChannel implements ChannelInterface
{
    /** Метка замаскированного плоского плейсхолдера темы. */
    private const FLAT_MARKER = '3';

    protected modX $modx;

    private TemplateTagGuard $tagGuard;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
        $this->tagGuard = new TemplateTagGuard();
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
            $nonce = $this->tagGuard->nonce();
            $placeholders = $this->tagGuard->neutralize($placeholders, $nonce);

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

        return $nonce === null ? $body : $this->tagGuard->restore($body, $nonce);
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

        $nonce = $this->tagGuard->nonce();

        // Плоские {key} прячем в самом шаблоне. Fenom такой синтаксис своим не
        // считает, но спотыкается на нём при компиляции: тема вида
        // «Заказ {num} / {$status_name}» валила разбор, а pdoTools на ошибке
        // отдаёт пустую строку — тема уходила пустой.
        [$subject, $flatValues] = $this->maskFlatPlaceholders($subject, $placeholders, $nonce);

        // Значения тоже под защиту: getChunk() после Fenom сам подставляет
        // [[+key]] и прогоняет остаток через разбор MODX-тегов
        // (pdotools CoreTools::getChunk), поэтому данные покупателя, вставленные
        // Fenom через {$order_comment} или {$address.first_name}, доезжали до
        // парсера как шаблон.
        $placeholders = $this->tagGuard->neutralize($placeholders, $nonce);

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

        // Сначала значения плоских ключей, потом открывающие последовательности.
        // Обратный порядок рвал маркер: он заканчивается меткой, и стоящая
        // сразу за ним цифра 1 или 2 складывалась с ней в то, что ищет
        // restore() — тема «Заказ {num}1 шт» уезжала с мусором вместо
        // номера. После разворачивания висячей метки в строке не остаётся:
        // нейтрализованные значения держат её только в составе пары, а сырое
        // значение плоского ключа 18-значную метку содержать не может.
        //
        // Оба шага идут после парсеров, поэтому шаблоном значение не станет.
        // strtr не проходит по только что вставленному тексту, так что
        // значение с {другим_ключом} внутри остаётся как есть.
        if ($flatValues !== []) {
            $subject = strtr($subject, $flatValues);
        }

        return $this->tagGuard->restore($subject, $nonce);
    }

    /**
     * Заменить в шаблоне темы плоские {key} на маркеры.
     *
     * Темы вида «Заказ {num}» держатся на этой подстановке: Fenom понимает
     * только {$key}. Берём лишь скаляры и лишь точное совпадение {key} —
     * без скобок подстановка затронула бы любое вхождение имени ключа.
     *
     * @param array<string, mixed> $placeholders
     * @return array{0: string, 1: array<string, string>} шаблон и карта маркер => значение
     */
    protected function maskFlatPlaceholders(string $subject, array $placeholders, string $nonce): array
    {
        if (!str_contains($subject, '{')) {
            return [$subject, []];
        }

        $values = [];
        $index = 0;

        foreach ($placeholders as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $tag = '{' . $key . '}';
            if (!str_contains($subject, $tag)) {
                continue;
            }

            $marker = $nonce . self::FLAT_MARKER . $index++ . $nonce;
            $subject = str_replace($tag, $marker, $subject);
            $values[$marker] = (string) $value;
        }

        return [$subject, $values];
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
