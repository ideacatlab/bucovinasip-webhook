<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = User::class;

    /**
     * Icon & navigation.
     */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers; // fallback to OutlinedRectangleStack if you prefer

    protected static ?int $navigationSort = 10;

    /**
     * Which attribute to use for record titles.
     */
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Labels (used in nav, breadcrumbs, action labels, etc.).
     */
    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Access Control');
    }

    public static function getNavigationBadge(): ?string
    {
        if (! hexa()->can('user.index')) {
            return null;
        }

        return (string) static::getModel()::query()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * Short description (mirrors Hexa's RoleResource style).
     * Useful for documentation / consistency (can be displayed in custom headers if you want).
     */
    public function userDescription(): string
    {
        return __('Manage application users, their roles, and multi-factor authentication status.');
    }

    /**
     * Hexa gates (permission keys) this resource uses.
     */
    public function defineGates(): array
    {
        return [
            'user.index' => __('Allows viewing the user list'),
            'user.create' => __('Allows creating a new user'),
            'user.update' => __('Allows updating users'),
            'user.delete' => __('Allows deleting users'),
        ];
    }

    /**
     * Hexa gate descriptions shown in Hexa’s UI (like RoleResource::defineGateDescriptions()).
     */
    public function defineGateDescriptions(): array
    {
        return [
            'user.index' => __('Allows administrators to access and view the users list'),
            'user.create' => __('Allows administrators to create new users and assign roles'),
            'user.update' => __('Allows administrators to modify existing users, roles, and MFA status'),
            'user.delete' => __('Allows administrators to delete users'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('user.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('user.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create users.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('user.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this user.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('user.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete this user.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('user.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete users.'));
    }

    /**
     * Form / Table (delegated to your schema classes).
     */
    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
