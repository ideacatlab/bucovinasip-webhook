# Brevo SDK Usage Guide

Custom lightweight SDK for sending transactional emails via Brevo API.

## Configuration

Add your Brevo API key to `.env`:

```env
BREVO_API_KEY=your-api-key-here

# Optional: Override default sender
BREVO_SENDER_EMAIL=noreply@yoursite.com
BREVO_SENDER_NAME="Your Site Name"
```

## Basic Usage

### 1. Send Email with Template

```php
use App\Services\Brevo\BrevoClient;
use App\Services\Brevo\BrevoEmail;

$client = app(BrevoClient::class);

$email = (new BrevoEmail())
    ->to('john.doe@example.com', 'John Doe')
    ->template(8) // Your template ID from Brevo
    ->params([
        'name' => 'John',
        'surname' => 'Doe',
        'custom_field' => 'Custom Value',
    ]);

$response = $client->sendTemplateEmail($email);

if ($response->isSuccessful()) {
    echo "Email sent! Message ID: " . $response->getMessageId();
} else {
    echo "Failed: " . $response->getError();
}
```

### 2. Using the Facade

```php
use App\Services\Brevo\Facades\Brevo;
use App\Services\Brevo\BrevoEmail;

$email = (new BrevoEmail())
    ->to('maria@example.com', 'Maria Garcia')
    ->template(10)
    ->params([
        'first_name' => 'Maria',
        'order_number' => '12345',
    ]);

$response = Brevo::sendTemplateEmail($email);
```

### 3. Send to Multiple Recipients

```php
$email = (new BrevoEmail())
    ->to('user1@example.com', 'User One')
    ->to('user2@example.com', 'User Two')
    ->to('user3@example.com')
    ->template(8)
    ->params(['greeting' => 'Hello Team']);

// Or use toMany()
$email = (new BrevoEmail())
    ->toMany([
        ['email' => 'user1@example.com', 'name' => 'User One'],
        ['email' => 'user2@example.com', 'name' => 'User Two'],
        'user3@example.com', // Just email
    ])
    ->template(8)
    ->params(['greeting' => 'Hello Team']);
```

### 4. Custom Sender

```php
$email = (new BrevoEmail())
    ->from('custom@example.com', 'Custom Sender')
    ->to('recipient@example.com')
    ->template(8)
    ->params(['name' => 'John']);
```

### 5. Add Custom Headers

```php
$email = (new BrevoEmail())
    ->to('user@example.com')
    ->template(8)
    ->params(['name' => 'John'])
    ->headers([
        'X-Mailin-custom' => 'custom_header_1:value1|custom_header_2:value2',
        'charset' => 'iso-8859-1',
    ])
    ->header('X-Custom-Tracking', 'campaign-123');
```

### 6. Add CC, BCC, Reply-To

```php
$email = (new BrevoEmail())
    ->to('primary@example.com', 'Primary User')
    ->cc('manager@example.com', 'Manager')
    ->bcc('archive@example.com')
    ->replyTo('support@example.com', 'Support Team')
    ->template(8)
    ->params(['name' => 'John']);
```

### 7. Add Tags for Tracking

```php
$email = (new BrevoEmail())
    ->to('user@example.com')
    ->template(8)
    ->params(['name' => 'John'])
    ->tags(['newsletter', 'campaign-2025'])
    ->tag('welcome-series');
```

### 8. Send Without Template (HTML Content)

```php
$email = (new BrevoEmail())
    ->to('user@example.com', 'John Doe')
    ->from('sender@example.com', 'Sender Name')
    ->subject('Your Order Confirmation')
    ->html('<h1>Thank you for your order!</h1><p>Order #12345</p>')
    ->text('Thank you for your order! Order #12345'); // Optional plain text

$response = Brevo::sendEmail($email);
```

## Advanced Usage

### Fluent Builder Pattern

```php
$response = Brevo::sendTemplateEmail(
    (new BrevoEmail())
        ->to('customer@example.com', 'Customer Name')
        ->template(15)
        ->params([
            'order_id' => '12345',
            'total' => '$99.99',
            'items' => [
                ['name' => 'Product 1', 'price' => '$49.99'],
                ['name' => 'Product 2', 'price' => '$49.99'],
            ],
        ])
        ->tags(['transactional', 'order-confirmation'])
        ->replyTo('support@yoursite.com', 'Support Team')
);
```

### Handle Response

```php
$response = Brevo::sendTemplateEmail($email);

// Check success
if ($response->isSuccessful()) {
    $messageId = $response->getMessageId();
    $data = $response->getData();

    Log::info('Email sent', ['message_id' => $messageId]);
}

// Handle failure
if ($response->failed()) {
    $error = $response->getError();
    $statusCode = $response->getStatusCode();

    Log::error('Email failed', [
        'error' => $error,
        'status' => $statusCode,
    ]);
}

// Or throw exception on failure
$response->throw(); // Throws BrevoException if failed
```

### Test Connection

```php
use App\Services\Brevo\Facades\Brevo;

if (Brevo::testConnection()) {
    echo "API connection successful!";
} else {
    echo "API connection failed!";
}
```

### Get Account Info

```php
$account = Brevo::getAccount();

echo "Email: " . $account['email'];
echo "Plan: " . $account['plan'][0]['type'];
```

### List Templates

```php
$templates = Brevo::getTemplates();

foreach ($templates as $template) {
    echo "ID: {$template['id']} - {$template['name']}\n";
}
```

### Get Specific Template

```php
$template = Brevo::getTemplate(8);

echo "Name: " . $template['name'];
echo "Subject: " . $template['subject'];
```

## Usage with Metform Webhooks

### Process Webhook and Send Email

```php
use App\Models\MetformWebhook;
use App\Services\Brevo\Facades\Brevo;
use App\Services\Brevo\BrevoEmail;

$webhook = MetformWebhook::unprocessed()->first();

try {
    $email = (new BrevoEmail())
        ->to($webhook->getEmail(), $webhook->getName())
        ->template(8) // Your template ID
        ->params([
            'name' => $webhook->getName(),
            'email' => $webhook->getEmail(),
            'phone' => $webhook->getPhone(),
            'message' => $webhook->getMessage(),
            'subject' => $webhook->getSubject(),
        ])
        ->tags(['metform', 'contact-form']);

    $response = Brevo::sendTemplateEmail($email);

    if ($response->isSuccessful()) {
        $webhook->markAsProcessed();
    } else {
        $webhook->markAsFailed($response->getError());
    }

} catch (\Exception $e) {
    $webhook->markAsFailed($e->getMessage());
}
```

### Artisan Command Example

```php
<?php

namespace App\Console\Commands;

use App\Models\MetformWebhook;
use App\Services\Brevo\Facades\Brevo;
use App\Services\Brevo\BrevoEmail;
use Illuminate\Console\Command;

class ProcessWebhooksCommand extends Command
{
    protected $signature = 'webhooks:process';

    protected $description = 'Process unprocessed webhooks and send to Brevo';

    public function handle()
    {
        $webhooks = MetformWebhook::unprocessed()->get();

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks to process');
            return;
        }

        $this->info("Processing {$webhooks->count()} webhooks...");

        $bar = $this->output->createProgressBar($webhooks->count());

        $success = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            try {
                $email = (new BrevoEmail())
                    ->to($webhook->getEmail(), $webhook->getName())
                    ->template(config('brevo.contact_form_template_id'))
                    ->params([
                        'name' => $webhook->getName() ?? 'Guest',
                        'email' => $webhook->getEmail(),
                        'phone' => $webhook->getPhone() ?? 'N/A',
                        'message' => $webhook->getMessage() ?? 'No message',
                        'subject' => $webhook->getSubject() ?? 'Contact Form',
                    ]);

                $response = Brevo::sendTemplateEmail($email);

                if ($response->isSuccessful()) {
                    $webhook->markAsProcessed();
                    $success++;
                } else {
                    $webhook->markAsFailed($response->getError());
                    $failed++;
                }

            } catch (\Exception $e) {
                $webhook->markAsFailed($e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✓ Success: {$success}");

        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }
    }
}
```

## Error Handling

### Try-Catch

```php
use App\Services\Brevo\BrevoException;

try {
    $response = Brevo::sendTemplateEmail($email);
    $response->throw(); // Throws exception if failed

    // Success
    echo "Email sent!";

} catch (BrevoException $e) {
    Log::error('Brevo error: ' . $e->getMessage());
}
```

### Check Response

```php
$response = Brevo::sendTemplateEmail($email);

if ($response->failed()) {
    // Handle failure
    $error = $response->getError();
    $statusCode = $response->getStatusCode();

    if ($statusCode === 401) {
        // Invalid API key
    } elseif ($statusCode === 400) {
        // Bad request
    }
}
```

## API Methods Reference

### BrevoClient

- `sendTemplateEmail(BrevoEmail $email): BrevoResponse`
- `sendEmail(BrevoEmail $email): BrevoResponse`
- `getAccount(): array`
- `getTemplates(): array`
- `getTemplate(int $templateId): array`
- `testConnection(): bool`

### BrevoEmail

**Recipients:**
- `to(string $email, ?string $name = null): self`
- `toMany(array $recipients): self`
- `cc(string $email, ?string $name = null): self`
- `bcc(string $email, ?string $name = null): self`

**Content:**
- `template(int $templateId): self`
- `params(array $params): self`
- `param(string $key, mixed $value): self`
- `subject(string $subject): self`
- `html(string $content): self`
- `text(string $content): self`

**Sender:**
- `from(string $email, ?string $name = null): self`
- `replyTo(string $email, ?string $name = null): self`

**Metadata:**
- `headers(array $headers): self`
- `header(string $key, string $value): self`
- `tags(array $tags): self`
- `tag(string $tag): self`

### BrevoResponse

- `isSuccessful(): bool`
- `failed(): bool`
- `getMessageId(): ?string`
- `getError(): ?string`
- `getData(): array`
- `getStatusCode(): ?int`
- `throw(): self` - Throws exception if failed

## Testing

### Test API Connection

```bash
php artisan tinker
```

```php
use App\Services\Brevo\Facades\Brevo;

// Test connection
Brevo::testConnection();

// Get account info
Brevo::getAccount();

// List templates
Brevo::getTemplates();
```

### Send Test Email

```php
use App\Services\Brevo\Facades\Brevo;
use App\Services\Brevo\BrevoEmail;

$response = Brevo::sendTemplateEmail(
    (new BrevoEmail())
        ->to('your-email@example.com', 'Test User')
        ->template(8)
        ->params(['name' => 'Test'])
);

dump($response->toArray());
```

## Notes

- All methods use fluent interface for easy chaining
- Logging is built-in for all API requests
- HTTP client has retry logic (3 attempts with 100ms delay)
- 30-second timeout on all requests
- API key is stored securely in `.env`
- Service provider registers singleton for efficient usage

## Troubleshooting

**API Key Error:**
```
InvalidArgumentException: Brevo API key is required
```
→ Add `BREVO_API_KEY` to your `.env` file

**401 Unauthorized:**
→ Check your API key is correct

**400 Bad Request:**
→ Check your template ID and params match template variables

**Template not found:**
→ Verify template ID exists in your Brevo account
