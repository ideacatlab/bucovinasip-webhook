<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(),

                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean()
                    ->state(fn (User $record) => filled($record->getAppAuthenticationSecret()))
                    ->tooltip(fn (User $record) => filled($record->getAppAuthenticationSecret()) ? 'Enabled' : 'Disabled'),

                TextColumn::make('email_verified_at')
                    ->label('Verified at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by verified / unverified
                TernaryFilter::make('is_verified')
                    ->label('Verified')
                    ->trueLabel('Only verified')
                    ->falseLabel('Only unverified')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                        blank: fn ($query) => $query,
                    ),

                // Filter by role
                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => hexa()->can('user.update')),

                Action::make('disableMfa')
                    ->label('Disable MFA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => filled($record->getAppAuthenticationSecret()) && hexa()->can('user.update'))
                    ->action(function (User $record) {
                        // Clear MFA secret & recovery codes
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);

                        Notification::make()
                            ->title('MFA disabled for user')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => hexa()->can('user.delete')),
                ]),
            ]);
    }
}
