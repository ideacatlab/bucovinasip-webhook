<?php

namespace App\Filament\Resources\MetformWebhooks\Pages;

use App\Filament\Resources\MetformWebhooks\MetformWebhookResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMetformWebhook extends EditRecord
{
    protected static string $resource = MetformWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
