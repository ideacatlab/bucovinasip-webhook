<?php

namespace App\Filament\Resources\MetformWebhooks\Pages;

use App\Filament\Resources\MetformWebhooks\MetformWebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetformWebhooks extends ListRecords
{
    protected static string $resource = MetformWebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
