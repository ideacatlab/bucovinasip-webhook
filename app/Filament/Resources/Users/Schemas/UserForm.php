<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),

                DateTimePicker::make('email_verified_at')
                    ->label('Verified at')
                    ->seconds(false),

                // Hexa roles selection (many-to-many)
                Select::make('roles')
                    ->label(__('Roles'))
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('guard', 'web'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->placeholder(__('Superuser (no role)')),

                // Read-only MFA status (users enable MFA themselves in their Profile)
                Placeholder::make('mfa_status')
                    ->label('MFA')
                    ->content(fn ($record) => $record && filled($record->getAppAuthenticationSecret()) ? 'Enabled' : 'Disabled')
                    ->visible(fn ($record, string $operation) => $operation === 'edit'),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    // Only save if provided (edit keeps old password if empty)
                    ->dehydrated(fn ($state) => filled($state)),
            ]);
    }
}
