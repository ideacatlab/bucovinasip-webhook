<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property array<array-key, mixed> $payload
 * @property bool $processed
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook processed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook unprocessed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MetformWebhook withErrors()
 *
 * @mixin \Eloquent
 *
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
 */
class MetformWebhook extends Model
{
    protected $fillable = [
        'payload',
        'processed',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Scope to get only unprocessed webhooks
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope to get only processed webhooks
     */
    public function scopeProcessed($query)
    {
        return $query->where('processed', true);
    }

    /**
     * Scope to get webhooks with errors
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Get a specific field from the payload
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getPayloadField(string $field, $default = null)
    {
        return data_get($this->payload, $field, $default);
    }

    /**
     * Get email from payload (common field)
     */
    public function getEmail(): ?string
    {
        return $this->getPayloadField('email') ?? $this->getPayloadField('user_email');
    }

    /**
     * Get name from payload (checks multiple common fields)
     */
    public function getName(): ?string
    {
        return $this->getPayloadField('name')
            ?? $this->getPayloadField('full_name')
            ?? trim(($this->getPayloadField('first_name') ?? '').' '.($this->getPayloadField('last_name') ?? ''))
            ?: null;
    }

    /**
     * Get phone from payload
     */
    public function getPhone(): ?string
    {
        return $this->getPayloadField('phone') ?? $this->getPayloadField('telephone');
    }

    /**
     * Get message from payload
     */
    public function getMessage(): ?string
    {
        return $this->getPayloadField('message') ?? $this->getPayloadField('comment');
    }

    /**
     * Get subject from payload
     */
    public function getSubject(): ?string
    {
        return $this->getPayloadField('subject') ?? $this->getPayloadField('topic');
    }

    /**
     * Check if payload contains a specific field
     */
    public function hasPayloadField(string $field): bool
    {
        return isset($this->payload[$field]) && filled($this->payload[$field]);
    }

    /**
     * Mark webhook as processed
     */
    public function markAsProcessed(?string $note = null): bool
    {
        return $this->update([
            'processed' => true,
            'processed_at' => now(),
            'error_message' => $note,
        ]);
    }

    /**
     * Mark webhook as failed with error message
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'processed' => false,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get all standard form fields in a structured array
     * Useful for passing to Brevo API
     */
    public function toBrevoData(): array
    {
        return [
            'email' => $this->getEmail(),
            'name' => $this->getName(),
            'phone' => $this->getPhone(),
            'message' => $this->getMessage(),
            'subject' => $this->getSubject(),
            'raw_payload' => $this->payload,
        ];
    }

    /**
     * Get a summary of the webhook for display
     */
    public function getSummary(): string
    {
        $parts = [];

        if ($name = $this->getName()) {
            $parts[] = "Name: {$name}";
        }

        if ($email = $this->getEmail()) {
            $parts[] = "Email: {$email}";
        }

        if ($subject = $this->getSubject()) {
            $parts[] = "Subject: {$subject}";
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'No standard fields';
    }
}
