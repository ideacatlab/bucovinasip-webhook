<?php

namespace App\Services\Brevo;

class BrevoEmail
{
    protected array $to = [];

    protected ?int $templateId = null;

    protected array $params = [];

    protected ?array $sender = null;

    protected ?string $subject = null;

    protected ?string $htmlContent = null;

    protected ?string $textContent = null;

    protected array $headers = [];

    protected array $cc = [];

    protected array $bcc = [];

    protected array $attachment = [];

    protected array $replyTo = [];

    protected ?array $tags = null;

    /**
     * Add a recipient
     */
    public function to(string $email, ?string $name = null): self
    {
        $this->to[] = array_filter([
            'email' => $email,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * Add multiple recipients
     */
    public function toMany(array $recipients): self
    {
        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $this->to($recipient['email'], $recipient['name'] ?? null);
            } else {
                $this->to($recipient);
            }
        }

        return $this;
    }

    /**
     * Set the template ID
     */
    public function template(int $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    /**
     * Set template parameters
     */
    public function params(array $params): self
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * Set a single template parameter
     */
    public function param(string $key, mixed $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Set the sender
     */
    public function from(string $email, ?string $name = null): self
    {
        $this->sender = array_filter([
            'email' => $email,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * Set the subject (for non-template emails)
     */
    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Set HTML content (for non-template emails)
     */
    public function html(string $content): self
    {
        $this->htmlContent = $content;

        return $this;
    }

    /**
     * Set text content (for non-template emails)
     */
    public function text(string $content): self
    {
        $this->textContent = $content;

        return $this;
    }

    /**
     * Add custom headers
     */
    public function headers(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Add a single custom header
     */
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Add CC recipient
     */
    public function cc(string $email, ?string $name = null): self
    {
        $this->cc[] = array_filter([
            'email' => $email,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * Add BCC recipient
     */
    public function bcc(string $email, ?string $name = null): self
    {
        $this->bcc[] = array_filter([
            'email' => $email,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * Set reply-to address
     */
    public function replyTo(string $email, ?string $name = null): self
    {
        $this->replyTo = array_filter([
            'email' => $email,
            'name' => $name,
        ]);

        return $this;
    }

    /**
     * Add tags for tracking
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Add a single tag
     */
    public function tag(string $tag): self
    {
        if ($this->tags === null) {
            $this->tags = [];
        }

        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Get the template ID
     */
    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    /**
     * Convert to array for API request
     */
    public function toArray(): array
    {
        $payload = [
            'to' => $this->to,
        ];

        if ($this->templateId) {
            $payload['templateId'] = $this->templateId;
        }

        if (! empty($this->params)) {
            $payload['params'] = $this->params;
        }

        if ($this->sender) {
            $payload['sender'] = $this->sender;
        }

        if ($this->subject) {
            $payload['subject'] = $this->subject;
        }

        if ($this->htmlContent) {
            $payload['htmlContent'] = $this->htmlContent;
        }

        if ($this->textContent) {
            $payload['textContent'] = $this->textContent;
        }

        if (! empty($this->headers)) {
            $payload['headers'] = $this->headers;
        }

        if (! empty($this->cc)) {
            $payload['cc'] = $this->cc;
        }

        if (! empty($this->bcc)) {
            $payload['bcc'] = $this->bcc;
        }

        if (! empty($this->replyTo)) {
            $payload['replyTo'] = $this->replyTo;
        }

        if ($this->tags !== null) {
            $payload['tags'] = $this->tags;
        }

        return $payload;
    }

    /**
     * Validate the email data
     */
    public function validate(): void
    {
        if (empty($this->to)) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        if ($this->templateId === null && empty($this->htmlContent) && empty($this->textContent)) {
            throw new \InvalidArgumentException('Either templateId or htmlContent/textContent is required');
        }

        if ($this->templateId === null && empty($this->subject)) {
            throw new \InvalidArgumentException('Subject is required for non-template emails');
        }
    }
}
