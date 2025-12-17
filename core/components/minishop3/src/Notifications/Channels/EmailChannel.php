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
        $body = $message->getBody();

        // If chunk is specified, render it
        if ($chunkName = $message->getChunk()) {
            $placeholders = array_merge(
                $notification->getPlaceholders(),
                $message->getPlaceholders()
            );

            // Use pdoTools for Fenom rendering
            if ($this->modx->services->has(Fetch::class)) {
                /** @var Fetch $pdoFetch */
                $pdoFetch = $this->modx->services->get(Fetch::class);
                $body = $pdoFetch->getChunk($chunkName, $placeholders);
            } else {
                $body = $this->modx->getChunk($chunkName, $placeholders);
            }
        }

        // Process MODX tags
        $this->modx->getParser()->processElementTags('', $body, true, false, '[[', ']]', [], 10);
        $this->modx->getParser()->processElementTags('', $body, true, true, '[[', ']]', [], 10);

        return $body;
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

        // Use pdoTools Fenom parser for subject (supports Fenom and MODX syntax)
        if ($this->modx->services->has(Fetch::class)) {
            /** @var Fetch $pdoFetch */
            $pdoFetch = $this->modx->services->get(Fetch::class);
            $subject = $pdoFetch->getChunk('@INLINE ' . $subject, $placeholders);
        } else {
            // Fallback: simple placeholder replacement + MODX parser
            foreach ($placeholders as $key => $value) {
                if (is_scalar($value)) {
                    $subject = str_replace("{{$key}}", (string) $value, $subject);
                    $subject = str_replace("{$key}", (string) $value, $subject);
                }
            }
            // Process MODX tags (lexicon, settings, etc.)
            $this->modx->getParser()->processElementTags('', $subject, true, false, '[[', ']]', [], 10);
            $this->modx->getParser()->processElementTags('', $subject, true, true, '[[', ']]', [], 10);
        }

        return $subject;
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
