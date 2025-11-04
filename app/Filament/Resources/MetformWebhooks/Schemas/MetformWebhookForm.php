<?php

namespace App\Filament\Resources\MetformWebhooks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MetformWebhookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Main payload section
                Section::make('Webhook Payload Data')
                    ->description('The complete data received from the Metform webhook. This contains all form fields submitted.')
                    ->icon('heroicon-o-document-text')
                    ->columnSpan(2)
                    ->schema([
                        KeyValue::make('payload')
                            ->label('Form Fields')
                            ->keyLabel('Field Name')
                            ->valueLabel('Field Value')
                            ->reorderable(false)
                            ->addable(true)
                            ->deletable(true)
                            ->editableKeys(true)
                            ->required()
                            ->columnSpanFull()
                            ->helperText(new HtmlString(
                                '<div class="space-y-2 mt-2">'.
                                '<p class="text-sm text-gray-600">Add form field data as key-value pairs. Common fields include:</p>'.
                                '<ul class="text-xs text-gray-500 list-disc list-inside space-y-1">'.
                                '<li><strong>name</strong>, <strong>first_name</strong>, <strong>last_name</strong> - Visitor name fields</li>'.
                                '<li><strong>email</strong> - Email address for Brevo API</li>'.
                                '<li><strong>phone</strong> - Contact phone number</li>'.
                                '<li><strong>subject</strong> - Form subject/topic</li>'.
                                '<li><strong>message</strong> - Main message content</li>'.
                                '</ul>'.
                                '</div>'
                            )),

                        Placeholder::make('usage_info')
                            ->label('Using This Data with Brevo API')
                            ->content(new HtmlString(
                                '<div class="p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-2">'.
                                '<p class="text-sm font-medium text-blue-900">Example: Accessing Payload Data</p>'.
                                '<pre class="text-xs bg-white p-3 rounded border border-blue-100 overflow-x-auto">'.
                                '$webhook = MetformWebhook::where(\'processed\', false)->first();'."\n\n".
                                '// Access individual fields'."\n".
                                '$email = $webhook->payload[\'email\'];'."\n".
                                '$name = $webhook->payload[\'name\'];'."\n".
                                '$message = $webhook->payload[\'message\'];'."\n\n".
                                '// Use in Brevo API call'."\n".
                                'Http::post(\'https://api.brevo.com/v3/smtp/email\', ['."\n".
                                '    \'to\' => [[\'email\' => $email, \'name\' => $name]],'."\n".
                                '    \'subject\' => \'New Contact Form\','."\n".
                                '    \'htmlContent\' => $message,'."\n".
                                ']);'.
                                '</pre>'.
                                '</div>'
                            )),
                    ]),

                // Processing status section
                Section::make('Processing Status')
                    ->description('Track the processing state and any errors')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('processed')
                            ->label('Processed')
                            ->helperText('Mark as processed when data has been sent to Brevo API')
                            ->default(false)
                            ->inline(false),

                        DateTimePicker::make('processed_at')
                            ->label('Processed At')
                            ->helperText('Timestamp when this webhook was processed')
                            ->seconds(false)
                            ->displayFormat('M j, Y g:i A')
                            ->visible(fn ($get) => $get('processed')),

                        Textarea::make('error_message')
                            ->label('Error Message')
                            ->placeholder('Any error that occurred during processing')
                            ->helperText('Record errors from Brevo API calls for debugging')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                // Metadata section
                Section::make('Metadata')
                    ->description('System-generated information')
                    ->icon('heroicon-o-information-circle')
                    ->columnSpan(3)
                    ->collapsed()
                    ->schema([
                        Placeholder::make('created_at')
                            ->label('Received At')
                            ->content(fn ($record) => $record?->created_at?->format('F j, Y \a\t g:i A') ?? 'Not yet saved'),

                        Placeholder::make('updated_at')
                            ->label('Last Updated')
                            ->content(fn ($record) => $record?->updated_at?->format('F j, Y \a\t g:i A') ?? 'Not yet saved'),

                        Placeholder::make('webhook_id')
                            ->label('Webhook ID')
                            ->content(fn ($record) => $record?->id ?? 'Will be assigned after creation'),
                    ]),

                // Integration guide section
                Section::make('Brevo Integration Guide')
                    ->description('How to use webhook data with Brevo Transactional API')
                    ->icon('heroicon-o-book-open')
                    ->columnSpan(3)
                    ->collapsed()
                    ->schema([
                        Placeholder::make('brevo_guide')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="prose prose-sm max-w-none">'.
                                '<h3 class="text-lg font-semibold mb-3">Integration Steps</h3>'.
                                '<ol class="space-y-3 text-sm">'.
                                '<li>'.
                                '<strong>Query Unprocessed Webhooks:</strong>'.
                                '<pre class="mt-1 bg-gray-50 p-2 rounded text-xs">$webhooks = MetformWebhook::where(\'processed\', false)->get();</pre>'.
                                '</li>'.
                                '<li>'.
                                '<strong>Extract Required Data:</strong>'.
                                '<pre class="mt-1 bg-gray-50 p-2 rounded text-xs">foreach ($webhooks as $webhook) {'."\n".
                                '    $data = ['."\n".
                                '        \'email\' => $webhook->payload[\'email\'] ?? null,'."\n".
                                '        \'name\' => $webhook->payload[\'name\'] ?? \'Guest\','."\n".
                                '        \'message\' => $webhook->payload[\'message\'] ?? \'\','."\n".
                                '    ];'."\n".
                                '}</pre>'.
                                '</li>'.
                                '<li>'.
                                '<strong>Call Brevo API:</strong>'.
                                '<pre class="mt-1 bg-gray-50 p-2 rounded text-xs">use Illuminate\Support\Facades\Http;'."\n\n".
                                '$response = Http::withHeaders(['."\n".
                                '    \'api-key\' => config(\'services.brevo.key\'),'."\n".
                                '    \'content-type\' => \'application/json\','."\n".
                                '])->post(\'https://api.brevo.com/v3/smtp/email\', ['."\n".
                                '    \'sender\' => [\'email\' => \'noreply@yoursite.com\'],'."\n".
                                '    \'to\' => [[\'email\' => $data[\'email\'], \'name\' => $data[\'name\']]],'."\n".
                                '    \'subject\' => \'Contact Form Submission\','."\n".
                                '    \'htmlContent\' => $data[\'message\'],'."\n".
                                ']);</pre>'.
                                '</li>'.
                                '<li>'.
                                '<strong>Update Processing Status:</strong>'.
                                '<pre class="mt-1 bg-gray-50 p-2 rounded text-xs">if ($response->successful()) {'."\n".
                                '    $webhook->update(['."\n".
                                '        \'processed\' => true,'."\n".
                                '        \'processed_at\' => now(),'."\n".
                                '    ]);'."\n".
                                '} else {'."\n".
                                '    $webhook->update(['."\n".
                                '        \'error_message\' => $response->body(),'."\n".
                                '    ]);'."\n".
                                '}</pre>'.
                                '</li>'.
                                '</ol>'.
                                '<div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded">'.
                                '<p class="text-xs text-amber-800"><strong>Note:</strong> Store your Brevo API key in <code>.env</code> as <code>BREVO_API_KEY</code></p>'.
                                '</div>'.
                                '</div>'
                            )),
                    ]),
            ]);
    }
}
