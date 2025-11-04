<?php

namespace App\Filament\Resources\MetformWebhooks;

use App\Filament\Resources\MetformWebhooks\Pages\CreateMetformWebhook;
use App\Filament\Resources\MetformWebhooks\Pages\EditMetformWebhook;
use App\Filament\Resources\MetformWebhooks\Pages\ListMetformWebhooks;
use App\Filament\Resources\MetformWebhooks\Schemas\MetformWebhookForm;
use App\Filament\Resources\MetformWebhooks\Tables\MetformWebhooksTable;
use App\Models\MetformWebhook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class MetformWebhookResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = MetformWebhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Labels
     */
    public static function getModelLabel(): string
    {
        return __('Metform Webhook');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Metform Webhooks');
    }

    public static function getNavigationLabel(): string
    {
        return __('Metform Webhooks');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Integrations');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('metform-webhook.index')) {
            return null;
        }

        // Show count of unprocessed webhooks
        $unprocessedCount = static::getModel()::query()->where('processed', false)->count();

        return $unprocessedCount > 0 ? (string) $unprocessedCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Unprocessed webhooks');
    }

    /**
     * Description
     */
    public function webhookDescription(): string
    {
        return __('Manage incoming Metform webhooks from WordPress. View, process, and monitor webhook data for integration with Brevo transactional API.');
    }

    /**
     * Hexa gates (permission keys)
     */
    public function defineGates(): array
    {
        return [
            'metform-webhook.index' => __('Allows viewing the webhooks list'),
            'metform-webhook.create' => __('Allows creating webhook records manually'),
            'metform-webhook.update' => __('Allows updating webhook records'),
            'metform-webhook.delete' => __('Allows deleting webhook records'),
        ];
    }

    /**
     * Hexa gate descriptions
     */
    public function defineGateDescriptions(): array
    {
        return [
            'metform-webhook.index' => __('Allows administrators to access and view the Metform webhooks list'),
            'metform-webhook.create' => __('Allows administrators to manually create webhook records for testing'),
            'metform-webhook.update' => __('Allows administrators to modify webhook records and processing status'),
            'metform-webhook.delete' => __('Allows administrators to delete webhook records'),
        ];
    }

    /**
     * Authorization responses
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('metform-webhook.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('metform-webhook.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create webhook records.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('metform-webhook.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this webhook record.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('metform-webhook.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete this webhook record.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('metform-webhook.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete webhook records.'));
    }

    public static function form(Schema $schema): Schema
    {
        return MetformWebhookForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetformWebhooksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetformWebhooks::route('/'),
            'create' => CreateMetformWebhook::route('/create'),
            'edit' => EditMetformWebhook::route('/{record}/edit'),
        ];
    }
}
