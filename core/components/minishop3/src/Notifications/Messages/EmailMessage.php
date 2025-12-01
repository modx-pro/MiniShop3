<?php

namespace MiniShop3\Notifications\Messages;

/**
 * Email message builder
 *
 * Fluent interface for building email notifications
 */
class EmailMessage
{
    protected string $subject = '';
    protected string $body = '';
    protected ?string $chunk = null;
    protected array $placeholders = [];
    protected ?string $from = null;
    protected ?string $fromName = null;
    protected ?string $replyTo = null;
    protected array $attachments = [];

    /**
     * Set email subject
     *
     * @param string $subject
     * @return self
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Set email body directly
     *
     * @param string $body HTML content
     * @return self
     */
    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set chunk name for email body template
     *
     * @param string $chunkName
     * @return self
     */
    public function chunk(string $chunkName): self
    {
        $this->chunk = $chunkName;
        return $this;
    }

    /**
     * Set placeholders for template rendering
     *
     * @param array $placeholders
     * @return self
     */
    public function with(array $placeholders): self
    {
        $this->placeholders = array_merge($this->placeholders, $placeholders);
        return $this;
    }

    /**
     * Set sender email
     *
     * @param string $email
     * @param string|null $name
     * @return self
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->from = $email;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Set reply-to address
     *
     * @param string $email
     * @return self
     */
    public function replyTo(string $email): self
    {
        $this->replyTo = $email;
        return $this;
    }

    /**
     * Add attachment
     *
     * @param string $path File path
     * @param string|null $name Display name
     * @return self
     */
    public function attach(string $path, ?string $name = null): self
    {
        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
        ];
        return $this;
    }

    // Getters

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getChunk(): ?string
    {
        return $this->chunk;
    }

    public function getPlaceholders(): array
    {
        return $this->placeholders;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
