<?php

namespace App\Listeners;

use App\Events\WebhookReceived;
use App\Services\Brevo\BrevoEmail;
use App\Services\Brevo\Facades\Brevo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendBrevoEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        $webhook = $event->webhook;

        // Parse Metform entries if present
        $entries = $this->parseMetformEntries($webhook);

        // Check referrer URL to determine if we should process
        $referrerUrl = $webhook->getPayloadField('referrer_url');

        if (! $this->shouldProcessWebhook($referrerUrl)) {
            Log::info('Webhook skipped - referrer URL does not match allowed list', [
                'webhook_id' => $webhook->id,
                'referrer_url' => $referrerUrl,
            ]);

            $webhook->markAsProcessed('Skipped - referrer URL not in allowed list: '.$referrerUrl);

            return;
        }

        // Get email and name from webhook
        $email = $entries['mf-email']
            ?? $entries['email']
            ?? $webhook->getEmail();

        $firstName = $entries['mf-listing-fname']
            ?? $entries['first_name']
            ?? $entries['name']
            ?? $entries['firstname']
            ?? $webhook->getPayloadField('first_name')
            ?? $webhook->getPayloadField('name')
            ?? 'Guest';

        $pricemp = $entries['mf-pret-total']
            ?? $entries['pricemp']
            ?? $entries['price']
            ?? $entries['PRICEMP']
            ?? $webhook->getPayloadField('pricemp')
            ?? $webhook->getPayloadField('price')
            ?? '';

        if (empty($email)) {
            Log::warning('Webhook has no email address, skipping Brevo email', [
                'webhook_id' => $webhook->id,
            ]);

            $webhook->markAsFailed('No email address found in webhook data');

            return;
        }

        Log::info('Sending Brevo email for webhook', [
            'webhook_id' => $webhook->id,
            'email' => $email,
            'first_name' => $firstName,
        ]);

        // Check if Brevo is properly configured
        if (empty(config('services.brevo.api_key')) || config('services.brevo.api_key') === 'your-api-key-here') {
            Log::warning('Brevo API key not configured, marking as processed without sending', [
                'webhook_id' => $webhook->id,
            ]);

            $webhook->markAsProcessed('Simulated send - Brevo not configured. Would send to: '.$email.' with FIRSTNAME='.$firstName.', PRICEMP='.$pricemp);

            return;
        }

        try {
            // Get default sender from config
            $defaultSender = config('services.brevo.default_sender');

            // Get template ID from config
            $templateId = config('services.brevo.default_template_id');

            // Build and send email
            $brevoEmail = (new BrevoEmail)
                ->to($email, $firstName)
                ->template($templateId)
                ->params([
                    'FIRSTNAME' => $firstName,
                    'PRICEMP' => $pricemp,
                ]);

            // Set sender if configured
            if (! empty($defaultSender['email'])) {
                $brevoEmail->from(
                    $defaultSender['email'],
                    $defaultSender['name'] ?? null
                );
            }

            $response = Brevo::sendTemplateEmail($brevoEmail);

            if ($response->isSuccessful()) {
                // Email sent successfully, now add contact to list
                $this->addContactToBrevoList($email, $firstName, $pricemp);

                $webhook->markAsProcessed('Email sent via Brevo. Message ID: '.$response->getMessageId());

                Log::info('Brevo email sent successfully', [
                    'webhook_id' => $webhook->id,
                    'message_id' => $response->getMessageId(),
                ]);
            } else {
                $webhook->markAsFailed('Brevo API error: '.$response->getError());

                Log::error('Failed to send Brevo email', [
                    'webhook_id' => $webhook->id,
                    'error' => $response->getError(),
                    'status_code' => $response->getStatusCode(),
                ]);
            }
        } catch (\Exception $e) {
            $webhook->markAsFailed('Exception: '.$e->getMessage());

            Log::error('Exception while sending Brevo email', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger queue retry if needed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(WebhookReceived $event, \Throwable $exception): void
    {
        Log::error('SendBrevoEmail listener failed after retries', [
            'webhook_id' => $event->webhook->id,
            'error' => $exception->getMessage(),
        ]);

        $event->webhook->markAsFailed('Failed after retries: '.$exception->getMessage());
    }

    /**
     * Parse Metform entries JSON string
     */
    protected function parseMetformEntries($webhook): array
    {
        $entriesJson = $webhook->getPayloadField('entries');

        if (empty($entriesJson)) {
            return [];
        }

        // Decode the JSON string
        $entries = json_decode($entriesJson, true);

        if (! is_array($entries)) {
            Log::warning('Failed to parse Metform entries JSON', [
                'webhook_id' => $webhook->id,
                'entries' => $entriesJson,
            ]);

            return [];
        }

        Log::debug('Parsed Metform entries', [
            'webhook_id' => $webhook->id,
            'entries' => $entries,
        ]);

        return $entries;
    }

    /**
     * Check if webhook should be processed based on referrer URL
     */
    protected function shouldProcessWebhook(?string $referrerUrl): bool
    {
        if (empty($referrerUrl)) {
            // If no referrer URL, allow processing (backward compatibility)
            return true;
        }

        // Define allowed referrer URLs
        $allowedReferrers = [
            'https://proiectare.bucovinasip.ro/formular/',
            // Add more allowed URLs here as needed
            // 'https://example.com/contact/',
            // 'https://example.com/quote/',
        ];

        // Check if referrer URL matches any allowed URLs
        foreach ($allowedReferrers as $allowed) {
            if ($referrerUrl === $allowed || str_starts_with($referrerUrl, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add contact to Brevo list with attributes
     */
    protected function addContactToBrevoList(string $email, string $firstName, string $pricemp): void
    {
        try {
            $listId = config('services.brevo.default_list_id');

            if (empty($listId)) {
                Log::warning('Brevo list ID not configured, skipping contact addition', [
                    'email' => $email,
                ]);

                return;
            }

            // Prepare contact attributes
            $attributes = [
                'FIRSTNAME' => $firstName,
            ];

            // Add PRICEMP only if not empty
            if (! empty($pricemp)) {
                $attributes['PRICEMP'] = $pricemp;
            }

            $response = Brevo::addContactToList(
                email: $email,
                listId: $listId,
                attributes: $attributes,
                updateEnabled: true // Update if contact already exists
            );

            if ($response->isSuccessful()) {
                Log::info('Contact added to Brevo list successfully', [
                    'email' => $email,
                    'list_id' => $listId,
                    'attributes' => $attributes,
                ]);
            } else {
                Log::warning('Failed to add contact to Brevo list', [
                    'email' => $email,
                    'list_id' => $listId,
                    'error' => $response->getError(),
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail the whole process if contact addition fails
            Log::error('Exception while adding contact to Brevo list', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
