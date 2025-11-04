# Metform Webhook Integration Guide

## Overview

This application receives webhook data from WordPress Metform forms, stores it safely in the database, and provides tools to process and send that data to the Brevo Transactional API.

## Setup Complete

### Database
- **Table**: `metform_webhooks`
- **Migration**: `database/migrations/2025_11_03_195236_create_metform_webhooks_table.php`

### Model
- **Location**: `app/Models/MetformWebhook.php`
- Includes helper methods for easy data access
- Automatically casts JSON payload to array

### Webhook Endpoint
- **URL**: `POST /webhook/metform`
- **CSRF Protection**: Disabled for webhook routes
- **Controller**: `app/Http/Controllers/WebhookController.php:11`

### Filament Admin Panel
- **Navigation**: Integrations → Metform Webhooks
- **Permissions**: Full Hexa permissions implemented
- **Badge**: Shows count of unprocessed webhooks

---

## Webhook Configuration

### WordPress Metform Setup

1. Go to your WordPress admin panel
2. Navigate to **Metform → Settings → Webhook**
3. Set the webhook URL to:
   ```
   https://your-domain.com/webhook/metform
   ```
4. Save settings

All form submissions will now be automatically sent to your Laravel application.

---

## Using Webhook Data

### Quick Access Methods

The `MetformWebhook` model provides convenient methods to access common form fields:

```php
use App\Models\MetformWebhook;

$webhook = MetformWebhook::unprocessed()->first();

// Get standard fields
$email = $webhook->getEmail();        // email or user_email
$name = $webhook->getName();          // name, full_name, or first_name + last_name
$phone = $webhook->getPhone();        // phone or telephone
$message = $webhook->getMessage();    // message or comment
$subject = $webhook->getSubject();    // subject or topic

// Get any custom field
$customField = $webhook->getPayloadField('custom_field_name', 'default value');

// Check if field exists
if ($webhook->hasPayloadField('company')) {
    $company = $webhook->getPayloadField('company');
}

// Get all data formatted for Brevo
$brevoData = $webhook->toBrevoData();
```

### Query Scopes

```php
// Get unprocessed webhooks
$unprocessed = MetformWebhook::unprocessed()->get();

// Get processed webhooks
$processed = MetformWebhook::processed()->get();

// Get webhooks with errors
$errors = MetformWebhook::withErrors()->get();

// Combine scopes
$recentErrors = MetformWebhook::unprocessed()
    ->withErrors()
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

---

## Brevo API Integration

### Environment Setup

Add your Brevo API key to `.env`:

```env
BREVO_API_KEY=your-api-key-here
```

Add to `config/services.php`:

```php
'brevo' => [
    'key' => env('BREVO_API_KEY'),
],
```

### Basic Integration Example

Create a command or service to process webhooks:

```php
use App\Models\MetformWebhook;
use Illuminate\Support\Facades\Http;

// Get unprocessed webhooks
$webhooks = MetformWebhook::unprocessed()->get();

foreach ($webhooks as $webhook) {
    // Get structured data
    $data = $webhook->toBrevoData();

    // Validate required fields
    if (empty($data['email'])) {
        $webhook->markAsFailed('Missing email address');
        continue;
    }

    try {
        // Call Brevo API
        $response = Http::withHeaders([
            'api-key' => config('services.brevo.key'),
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender' => [
                'name' => 'Your Site',
                'email' => 'noreply@yoursite.com',
            ],
            'to' => [
                [
                    'email' => $data['email'],
                    'name' => $data['name'] ?? 'Guest',
                ],
            ],
            'subject' => $data['subject'] ?? 'New Contact Form Submission',
            'htmlContent' => view('emails.contact-form', $data)->render(),
            // Or plain text
            // 'textContent' => $data['message'],
        ]);

        if ($response->successful()) {
            $webhook->markAsProcessed();
        } else {
            $webhook->markAsFailed($response->body());
        }

    } catch (\Exception $e) {
        $webhook->markAsFailed($e->getMessage());
    }
}
```

### Advanced: Create a Service Class

Create `app/Services/BrevoService.php`:

```php
<?php

namespace App\Services;

use App\Models\MetformWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://api.brevo.com/v3/smtp/email';

    public function __construct()
    {
        $this->apiKey = config('services.brevo.key');
    }

    public function processWebhook(MetformWebhook $webhook): bool
    {
        $data = $webhook->toBrevoData();

        if (empty($data['email'])) {
            $webhook->markAsFailed('Missing email address');
            return false;
        }

        try {
            $response = $this->sendEmail([
                'to' => [
                    [
                        'email' => $data['email'],
                        'name' => $data['name'] ?? 'Guest',
                    ],
                ],
                'subject' => $data['subject'] ?? 'Contact Form Submission',
                'htmlContent' => $this->formatMessage($data),
            ]);

            if ($response->successful()) {
                $webhook->markAsProcessed();
                return true;
            }

            $webhook->markAsFailed($response->body());
            return false;

        } catch (\Exception $e) {
            Log::error('Brevo API Error', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
            ]);

            $webhook->markAsFailed($e->getMessage());
            return false;
        }
    }

    protected function sendEmail(array $payload)
    {
        return Http::withHeaders([
            'api-key' => $this->apiKey,
            'content-type' => 'application/json',
        ])->post($this->apiUrl, array_merge([
            'sender' => [
                'name' => config('app.name'),
                'email' => config('mail.from.address'),
            ],
        ], $payload));
    }

    protected function formatMessage(array $data): string
    {
        $html = '<h2>New Contact Form Submission</h2>';
        $html .= '<p><strong>Name:</strong> ' . ($data['name'] ?? 'Not provided') . '</p>';
        $html .= '<p><strong>Email:</strong> ' . ($data['email'] ?? 'Not provided') . '</p>';

        if (!empty($data['phone'])) {
            $html .= '<p><strong>Phone:</strong> ' . $data['phone'] . '</p>';
        }

        if (!empty($data['message'])) {
            $html .= '<p><strong>Message:</strong></p>';
            $html .= '<p>' . nl2br(e($data['message'])) . '</p>';
        }

        return $html;
    }

    public function processAllUnprocessed(): array
    {
        $webhooks = MetformWebhook::unprocessed()->get();
        $stats = ['success' => 0, 'failed' => 0];

        foreach ($webhooks as $webhook) {
            if ($this->processWebhook($webhook)) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
```

### Create an Artisan Command

Create `app/Console/Commands/ProcessMetformWebhooks.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\BrevoService;
use Illuminate\Console\Command;

class ProcessMetformWebhooks extends Command
{
    protected $signature = 'metform:process';
    protected $description = 'Process unprocessed Metform webhooks and send to Brevo';

    public function handle(BrevoService $brevoService)
    {
        $this->info('Processing Metform webhooks...');

        $stats = $brevoService->processAllUnprocessed();

        $this->info("Processed: {$stats['success']} successful, {$stats['failed']} failed");

        return Command::SUCCESS;
    }
}
```

Then run:
```bash
php artisan metform:process
```

Or schedule it in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('metform:process')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
```

---

## Admin Panel Features

### Table View
- **Split Layout**: ID/Status | Preview | Details
- **Expandable Panels**: Click to view complete JSON payload
- **Filters**: Processing status, errors, date range
- **Actions**:
  - Mark as Processed/Unprocessed
  - View JSON in modal
  - Edit record
  - Delete

### Form View
- **Payload Section**: KeyValue editor for all form fields
- **Processing Status**: Toggle, timestamp, error message
- **Metadata**: System timestamps and IDs
- **Integration Guide**: Built-in documentation

### Permissions (Hexa)

The following permissions are available in the Hexa admin panel:

- `metform-webhook.index` - View webhooks list
- `metform-webhook.create` - Create webhook records (testing)
- `metform-webhook.update` - Edit webhooks and mark as processed
- `metform-webhook.delete` - Delete webhook records

---

## Testing the Webhook

### Manual Test with curl

```bash
curl -X POST https://your-domain.com/webhook/metform \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "123-456-7890",
    "subject": "Test Inquiry",
    "message": "This is a test message from Metform"
  }'
```

### Check the Database

```bash
php artisan tinker
```

```php
// See all webhooks
MetformWebhook::all();

// Get latest
$webhook = MetformWebhook::latest()->first();

// View payload
$webhook->payload;

// Test helper methods
$webhook->getEmail();
$webhook->getName();
$webhook->toBrevoData();
```

---

## Security Notes

- CSRF protection is disabled only for `/webhook/*` routes
- All webhook data is logged for debugging
- Payload is stored as JSON for safe data handling
- Admin access controlled by Hexa permissions

---

## Troubleshooting

### Webhook Not Receiving Data

1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify webhook URL in WordPress Metform settings
3. Test manually with curl command above
4. Check route is registered: `php artisan route:list --path=webhook`

### Brevo API Errors

1. Verify API key in `.env`
2. Check Brevo API limits and quota
3. Review error messages in webhook records
4. Check logs: `storage/logs/laravel.log`

### Permission Issues in Admin Panel

1. Ensure user has proper Hexa role
2. Grant permissions in Hexa admin: **Access Control → Roles**
3. Assign `metform-webhook.*` permissions to appropriate roles

---

## Next Steps

1. **Set up Brevo API key** in `.env`
2. **Create email templates** for better formatting
3. **Implement BrevoService** for automated processing
4. **Schedule command** to process webhooks periodically
5. **Test webhook** endpoint with Metform
6. **Configure permissions** in Hexa admin panel

---

## Support

For Laravel/Filament issues, check:
- Laravel docs: https://laravel.com/docs
- Filament docs: https://filamentphp.com/docs

For Brevo API:
- Brevo API docs: https://developers.brevo.com/
