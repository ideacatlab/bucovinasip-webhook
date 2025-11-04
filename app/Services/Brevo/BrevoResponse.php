<?php

namespace App\Services\Brevo;

class BrevoResponse
{
    public function __construct(
        public bool $success,
        public ?string $messageId,
        public array $data = [],
        public ?string $error = null,
        public ?int $statusCode = null
    ) {}

    /**
     * Check if the email was sent successfully
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if the request failed
     */
    public function failed(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the message ID
     */
    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * Get error message
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get response data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Throw exception if failed
     */
    public function throw(): self
    {
        if ($this->failed()) {
            throw new BrevoException(
                $this->error ?? 'Unknown error',
                $this->statusCode ?? 0
            );
        }

        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_id' => $this->messageId,
            'error' => $this->error,
            'status_code' => $this->statusCode,
            'data' => $this->data,
        ];
    }
}
