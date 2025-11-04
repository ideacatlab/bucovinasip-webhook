<?php

namespace App\Filament\Resources\MetformWebhooks\Tables;

use App\Models\MetformWebhook;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class MetformWebhooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Split::make([
                    Stack::make([
                        // ID with status badge
                        TextColumn::make('id')
                            ->label('ID')
                            ->weight(FontWeight::Bold)
                            ->color(Color::Blue)
                            ->badge()
                            ->searchable()
                            ->sortable(),

                        // Processing status
                        IconColumn::make('processed')
                            ->label('Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-clock')
                            ->trueColor('success')
                            ->falseColor('warning')
                            ->tooltip(fn (?MetformWebhook $record) => $record?->processed ? 'Processed' : 'Pending'),
                    ])->alignCenter(),

                    Stack::make([
                        // Key form fields from payload (if available)
                        TextColumn::make('payload_preview')
                            ->label('Form Data Preview')
                            ->getStateUsing(function (?MetformWebhook $record) {
                                if (! $record) {
                                    return 'No data';
                                }

                                $payload = $record->payload;
                                $preview = [];

                                // Extract common form fields
                                $commonFields = ['name', 'email', 'phone', 'subject', 'message', 'first_name', 'last_name'];

                                foreach ($commonFields as $field) {
                                    if (isset($payload[$field]) && filled($payload[$field])) {
                                        $preview[] = ucfirst($field).': '.str($payload[$field])->limit(30);
                                    }
                                }

                                return ! empty($preview) ? implode(' | ', $preview) : 'No standard fields detected';
                            })
                            ->html()
                            ->color(Color::Gray)
                            ->wrap(),

                        // Timestamps
                        TextColumn::make('created_at')
                            ->label('Received')
                            ->dateTime('M j, Y g:i A')
                            ->sortable()
                            ->color(Color::Slate)
                            ->size('sm'),
                    ])->grow(),

                    Stack::make([
                        // Processed timestamp
                        TextColumn::make('processed_at')
                            ->label('Processed')
                            ->dateTime('M j, Y g:i A')
                            ->placeholder('Not processed')
                            ->color(Color::Green)
                            ->size('sm'),

                        // Total fields count
                        TextColumn::make('fields_count')
                            ->label('Fields')
                            ->getStateUsing(fn (?MetformWebhook $record) => $record ? count($record->payload ?? []) : 0)
                            ->badge()
                            ->color(Color::Indigo),
                    ])->alignEnd(),
                ]),

                // Expandable panel with full JSON payload
                Panel::make([
                    Stack::make([
                        TextColumn::make('payload_display')
                            ->label('Complete Payload Data')
                            ->getStateUsing(function (?MetformWebhook $record) {
                                if (! $record) {
                                    return new HtmlString('<div class="text-gray-500">No data</div>');
                                }

                                $json = json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                                return new HtmlString(
                                    '<div class="mt-2">'.
                                    '<pre class="bg-gray-950 text-green-400 p-4 rounded-lg overflow-x-auto text-xs font-mono border border-gray-700">'.
                                    htmlspecialchars($json).
                                    '</pre>'.
                                    '</div>'
                                );
                            })
                            ->html(),

                        // Error message if present
                        TextColumn::make('error_message')
                            ->label('Error Message')
                            ->color(Color::Red)
                            ->visible(fn (?MetformWebhook $record) => $record && filled($record->error_message))
                            ->html()
                            ->getStateUsing(fn (?MetformWebhook $record) => $record && $record->error_message ? new HtmlString(
                                '<div class="mt-2 p-3 bg-red-50 border border-red-200 rounded-lg text-red-800">'.
                                '<strong>Error:</strong> '.nl2br(htmlspecialchars($record->error_message)).
                                '</div>'
                            ) : ''),
                    ]),
                ])->collapsible(),
            ])
            ->filters([
                // Filter by processed status
                TernaryFilter::make('processed')
                    ->label('Processing Status')
                    ->trueLabel('Processed only')
                    ->falseLabel('Unprocessed only')
                    ->queries(
                        true: fn ($query) => $query->where('processed', true),
                        false: fn ($query) => $query->where('processed', false),
                        blank: fn ($query) => $query,
                    ),

                // Filter by errors
                TernaryFilter::make('has_errors')
                    ->label('Has Errors')
                    ->trueLabel('With errors')
                    ->falseLabel('Without errors')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('error_message'),
                        false: fn ($query) => $query->whereNull('error_message'),
                        blank: fn ($query) => $query,
                    ),

                // Filter by date range
                SelectFilter::make('date_range')
                    ->label('Date Range')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'week' => 'Last 7 days',
                        'month' => 'Last 30 days',
                    ])
                    ->query(function ($query, $state) {
                        return match ($state['value'] ?? null) {
                            'today' => $query->whereDate('created_at', today()),
                            'yesterday' => $query->whereDate('created_at', today()->subDay()),
                            'week' => $query->where('created_at', '>=', now()->subDays(7)),
                            'month' => $query->where('created_at', '>=', now()->subDays(30)),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => hexa()->can('metform-webhook.update')),

                // Mark as processed action
                Action::make('markProcessed')
                    ->label('Mark Processed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (?MetformWebhook $record) => $record && ! $record->processed && hexa()->can('metform-webhook.update'))
                    ->action(function (MetformWebhook $record) {
                        $record->update([
                            'processed' => true,
                            'processed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Webhook marked as processed')
                            ->success()
                            ->send();
                    }),

                // Mark as unprocessed action
                Action::make('markUnprocessed')
                    ->label('Mark Unprocessed')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (?MetformWebhook $record) => $record && $record->processed && hexa()->can('metform-webhook.update'))
                    ->action(function (MetformWebhook $record) {
                        $record->update([
                            'processed' => false,
                            'processed_at' => null,
                            'error_message' => null,
                        ]);

                        Notification::make()
                            ->title('Webhook marked as unprocessed')
                            ->info()
                            ->send();
                    }),

                // View raw JSON in modal
                Action::make('viewJson')
                    ->label('View JSON')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->modalHeading('Webhook Payload')
                    ->modalContent(fn (?MetformWebhook $record) => $record ? view('filament.modals.json-viewer', [
                        'json' => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]) : '')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                // Bulk mark as processed
                BulkAction::make('markProcessed')
                    ->label('Mark as Processed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => hexa()->can('metform-webhook.update'))
                    ->action(function (Collection $records) {
                        $records->each(function (MetformWebhook $record) {
                            $record->update([
                                'processed' => true,
                                'processed_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title('Webhooks marked as processed')
                            ->success()
                            ->send();
                    }),

                DeleteBulkAction::make()
                    ->visible(fn () => hexa()->can('metform-webhook.delete')),
            ])
            ->emptyStateHeading('No webhooks received yet')
            ->emptyStateDescription('Webhooks from Metform will appear here once they are received.')
            ->emptyStateIcon('heroicon-o-inbox-arrow-down');
    }
}
