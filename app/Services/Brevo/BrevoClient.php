<?php

namespace App\Services\Brevo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoClient
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.brevo.com/v3';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.brevo.api_key');

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Brevo API key is required. Set BREVO_API_KEY in your .env file.');
        }
    }

    /**
     * Send a transactional email using a template
     */
    public function sendTemplateEmail(BrevoEmail $email): BrevoResponse
    {
        $payload = $email->toArray();

        Log::info('Sending Brevo template email', [
            'template_id' => $email->getTemplateId(),
            'recipients' => count($payload['to']),
        ]);

        try {
            $response = $this->makeRequest()
                ->post('/smtp/email', $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Brevo API error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new BrevoException('Failed to send email: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Send a simple transactional email without template
     */
    public function sendEmail(BrevoEmail $email): BrevoResponse
    {
        $payload = $email->toArray();

        Log::info('Sending Brevo email', [
            'has_template' => isset($payload['templateId']),
            'recipients' => count($payload['to']),
        ]);

        try {
            $response = $this->makeRequest()
                ->post('/smtp/email', $payload);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Brevo API error', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            throw new BrevoException('Failed to send email: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get account information
     */
    public function getAccount(): array
    {
        $response = $this->makeRequest()->get('/account');

        if ($response->successful()) {
            return $response->json();
        }

        throw new BrevoException('Failed to get account info: '.$response->body());
    }

    /**
     * Get list of templates
     */
    public function getTemplates(): array
    {
        $response = $this->makeRequest()->get('/smtp/templates');

        if ($response->successful()) {
            return $response->json('templates', []);
        }

        throw new BrevoException('Failed to get templates: '.$response->body());
    }

    /**
     * Get a specific template by ID
     */
    public function getTemplate(int $templateId): array
    {
        $response = $this->makeRequest()->get("/smtp/templates/{$templateId}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new BrevoException("Failed to get template {$templateId}: ".$response->body());
    }

    /**
     * Test the API connection
     */
    public function testConnection(): bool
    {
        try {
            $this->getAccount();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add or update a contact in Brevo
     *
     * @param  array<string, mixed>  $contactData
     */
    public function createOrUpdateContact(array $contactData): BrevoResponse
    {
        Log::info('Adding/updating Brevo contact', [
            'email' => $contactData['email'] ?? 'unknown',
            'list_ids' => $contactData['listIds'] ?? [],
        ]);

        try {
            $response = $this->makeRequest()
                ->post('/contacts', $contactData);

            return $this->handleResponse($response);
        } catch (\Exception $e) {
            Log::error('Brevo contact API error', [
                'error' => $e->getMessage(),
                'contact_data' => $contactData,
            ]);

            throw new BrevoException('Failed to add/update contact: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Add contact to a list with optional attributes
     */
    public function addContactToList(string $email, int $listId, array $attributes = [], bool $updateEnabled = false): BrevoResponse
    {
        $payload = [
            'email' => $email,
            'listIds' => [$listId],
            'updateEnabled' => $updateEnabled,
        ];

        if (! empty($attributes)) {
            $payload['attributes'] = $attributes;
        }

        return $this->createOrUpdateContact($payload);
    }

    /**
     * Create HTTP client with authentication
     */
    protected function makeRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 100);
    }

    /**
     * Handle API response
     */
    protected function handleResponse(Response $response): BrevoResponse
    {
        if ($response->successful()) {
            // Handle 204 No Content responses (contact updated)
            $data = $response->json() ?? [];

            return new BrevoResponse(
                success: true,
                messageId: $response->json('messageId'),
                data: $data,
                statusCode: $response->status()
            );
        }

        $error = $response->json('message', $response->body());
        $data = $response->json() ?? [];

        Log::error('Brevo API request failed', [
            'status' => $response->status(),
            'error' => $error,
            'body' => $response->body(),
        ]);

        return new BrevoResponse(
            success: false,
            messageId: null,
            data: $data,
            error: $error,
            statusCode: $response->status()
        );
    }
}
