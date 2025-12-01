<?php

namespace MiniShop3\Notifications\Messages;

/**
 * SMS message builder
 *
 * Fluent interface for building SMS notifications
 */
class SmsMessage
{
    protected string $content = '';
    protected ?string $from = null;

    /**
     * Set message content
     *
     * @param string $content Message text (limited by SMS standards)
     * @return self
     */
    public function content(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set sender ID (alphanumeric or phone number)
     *
     * @param string $from
     * @return self
     */
    public function from(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    // Getters

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * Get content length
     *
     * @return int
     */
    public function getLength(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * Check if message exceeds standard SMS length
     *
     * @param int $maxLength Default 160 for GSM-7, 70 for Unicode
     * @return bool
     */
    public function exceedsLength(int $maxLength = 160): bool
    {
        return $this->getLength() > $maxLength;
    }
}
