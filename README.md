# Turbocat Tall Starter Kit



## Hexa Lite permissions on a new Filament Resource (quick pattern)

### 1) Add gates to the Resource

```php
<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class PostResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Post::class;

    // Navigation & labels (optional but nice)
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;
    protected static ?int $navigationSort = 20;
    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string        { return __('Post'); }
    public static function getPluralModelLabel(): string  { return __('Posts'); }
    public static function getNavigationLabel(): string   { return __('Posts'); }
    public static function getNavigationGroup(): ?string  { return __('Content'); }

    // Short description (optional)
    public function postDescription(): string
    {
        return __('Manage posts and their publication lifecycle.');
    }

    // 1) Define permission keys for this resource
    public function defineGates(): array
    {
        return [
            'post.index'  => __('Allows viewing the post list'),
            'post.create' => __('Allows creating a new post'),
            'post.update' => __('Allows updating posts'),
            'post.delete' => __('Allows deleting posts'),
        ];
    }

    // 2) Human-readable descriptions (shown in Hexa UI)
    public function defineGateDescriptions(): array
    {
        return [
            'post.index'  => __('Access and view the posts list'),
            'post.create' => __('Create new posts'),
            'post.update' => __('Edit existing posts'),
            'post.delete' => __('Delete posts'),
        ];
    }

    // 3) Lock the resource with Hexa (Filament v4 authorization responses)
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('post.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('post.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create posts.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('post.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this post.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('post.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete this post.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('post.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete posts.'));
    }

    // (Optional) If you use these actions:
    public static function getReplicateAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('post.create') ? Response::allow() : Response::deny();
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('post.update') ? Response::allow() : Response::deny();
    }

    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('post.delete') ? Response::allow() : Response::deny();
    }

    // 4) Form / Table (define or delegate)
    public static function form(Schema $schema): Schema  { return $schema; }

    public static function table(Table $table): Table    { return $table; }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit'   => EditPost::route('/{record}/edit'),
        ];
    }
}
```

---

### 2) Gate buttons/sections in Table/Form (example)

```php
// In your table():
->recordActions([
    \Filament\Actions\EditAction::make()
        ->visible(fn () => hexa()->can('post.update')),
    \Filament\Actions\DeleteAction::make()
        ->visible(fn () => hexa()->can('post.delete')),
])
->headerActions([
    \Filament\Actions\CreateAction::make()
        ->visible(fn () => hexa()->can('post.create')),
]);

// In your form components:
\Filament\Forms\Components\Section::make('Advanced')
    ->visible(fn () => hexa()->can('post.update'))
    ->schema([
        // ...
    ]);
```

> Note: Filament v4 will also respect the authorization response methods for page access and actions, but using `visible()` is handy for hiding UI affordances when you want finer control.

---

### 3) Add the new permissions to a role

**Recommended:** Open your **Role & Permissions** panel and enable:

* `post.index`
* `post.create`
* `post.update`
* `post.delete`

(Optional) Seed them by writing to the role’s `access['role_permissions']` JSON in your seeders.

---

### 4) Naming convention

Use `resource.action` keys for clarity and consistency:

* `post.index`, `post.create`, `post.update`, `post.delete`
* `user.index`, `user.create`, …

---

### 5) Notes

* Ensure the **HexaLite plugin is registered before resource discovery** in your panel config so gates are discoverable.
* **Panel access** (who can log in) is separate from **resource access** (who can see/use a resource). Keep your `User::canAccessPanel()` (or equivalent) as needed.
* If a user can’t view a resource (`post.index` denied), the navigation item will be hidden and direct access to the resource pages will be denied, thanks to the v4 authorization responses.
