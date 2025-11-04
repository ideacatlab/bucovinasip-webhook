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

        // Get email and name from webhook
        $email = $webhook->getEmail();
        $firstName = $webhook->getPayloadField('first_name')
            ?? $webhook->getPayloadField('name')
            ?? $webhook->getPayloadField('firstname')
            ?? 'Guest';

        $pricemp = $webhook->getPayloadField('pricemp')
            ?? $webhook->getPayloadField('price')
            ?? $webhook->getPayloadField('PRICEMP')
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

            // Build and send email
            $brevoEmail = (new BrevoEmail)
                ->to($email, $firstName)
                ->template(105)
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
}
