<?php

namespace MiniShop3\Notifications\Messages;

/**
 * Telegram message builder
 *
 * Fluent interface for building Telegram notifications
 */
class TelegramMessage
{
    protected string $content = '';
    protected ?string $parseMode = 'HTML';
    protected bool $disableWebPagePreview = false;
    protected bool $disableNotification = false;
    protected ?array $replyMarkup = null;

    /**
     * Set message content
     *
     * @param string $content Message text
     * @return self
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set parse mode
     *
     * @param string|null $mode 'HTML', 'Markdown', 'MarkdownV2', or null
     * @return self
     */
    public function parseMode(?string $mode): self
    {
        $this->parseMode = $mode;
        return $this;
    }

    /**
     * Disable web page preview
     *
     * @param bool $disable
     * @return self
     */
    public function disableWebPagePreview(bool $disable = true): self
    {
        $this->disableWebPagePreview = $disable;
        return $this;
    }

    /**
     * Send silently (no notification sound)
     *
     * @param bool $silent
     * @return self
     */
    public function silent(bool $silent = true): self
    {
        $this->disableNotification = $silent;
        return $this;
    }

    /**
     * Add inline keyboard buttons
     *
     * @param array $buttons Array of button rows
     * @return self
     */
    public function buttons(array $buttons): self
    {
        $this->replyMarkup = [
            'inline_keyboard' => $buttons,
        ];
        return $this;
    }

    /**
     * Add single button row with URL
     *
     * @param string $text Button text
     * @param string $url Button URL
     * @return self
     */
    public function button(string $text, string $url): self
    {
        $this->replyMarkup = [
            'inline_keyboard' => [
                [['text' => $text, 'url' => $url]],
            ],
        ];
        return $this;
    }

    // Getters

    public function getContent(): string
    {
        return $this->content;
    }

    public function getParseMode(): ?string
    {
        return $this->parseMode;
    }

    public function getDisableWebPagePreview(): bool
    {
        return $this->disableWebPagePreview;
    }

    public function getDisableNotification(): bool
    {
        return $this->disableNotification;
    }

    public function getReplyMarkup(): ?array
    {
        return $this->replyMarkup;
    }

    /**
     * Build Telegram API payload
     *
     * @param string|int $chatId
     * @return array
     */
    public function toPayload(string|int $chatId): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $this->content,
        ];

        if ($this->parseMode) {
            $payload['parse_mode'] = $this->parseMode;
        }

        if ($this->disableWebPagePreview) {
            $payload['disable_web_page_preview'] = true;
        }

        if ($this->disableNotification) {
            $payload['disable_notification'] = true;
        }

        if ($this->replyMarkup) {
            $payload['reply_markup'] = json_encode($this->replyMarkup);
        }

        return $payload;
    }
}
