<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Notifications\VerifyEmailChange;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Concerns;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form as SchemaForm;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Js;
use Illuminate\Validation\Rules\Password;
use League\Uri\Components\Query;
use LogicException;
use Throwable;

class ManageProfileSettings extends Page
{
    use Concerns\CanUseDatabaseTransactions;
    use Concerns\HasMaxWidth;
    use Concerns\HasTopbar;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationLabel = 'Account Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'manage-profile-settings';

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.shared.manage-profile-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getTitle(): string|Htmlable
    {
        return __('Profile settings');
    }

    public static function getNavigationGroup(): ?string
    {
        $panel = \Filament\Facades\Filament::getCurrentPanel();

        return match ($panel?->getId()) {
            'admin' => 'Settings',
            // 'driver'   => 'Account',
            // 'employer' => 'Account',
            default => 'Account',
        };
    }

    public static function shouldRegisterNavigation(): bool
    {
        $panelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();

        return in_array($panelId, ['admin'], true); // example for extension to multiple pannels ['admin', driver', 'employer']
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return static::$slug ?? 'manage-profile-settings';
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $data = $this->getUser()->attributesToArray();

        $this->callHook('beforeFill');
        $data = $this->mutateFormDataBeforeFill($data);
        $this->form->fill($data);
        $this->callHook('afterFill');
    }

    public function getUser(): Authenticatable&Model
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            throw new LogicException('The authenticated user object must be an Eloquent model.');
        }

        return $user;
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'first_name',
            'last_name',
            'phone',
            'email',
        ]));
    }

    public function save(): void
    {
        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');
            $data = $this->form->getState();
            $this->callHook('afterValidate');

            $this->callHook('beforeSave');
            $this->handleRecordUpdate($this->getUser(), $data);
            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();
            throw $exception;
        }

        $this->commitDatabaseTransaction();

        if (request()->hasSession() && array_key_exists('password', $data)) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $data['password'],
            ]);
        }

        $this->data['password'] = null;
        $this->data['passwordConfirmation'] = null;
        $this->data['currentPassword'] = null;

        $this->getSavedNotification()?->send();
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (Filament::hasEmailChangeVerification() && array_key_exists('email', $data)) {
            $this->sendEmailChangeVerification($record, $data['email']);
            unset($data['email']);
        }

        $record->update($data);

        return $record;
    }

    protected function sendEmailChangeVerification(Model $record, string $newEmail): void
    {
        if ($record->getAttributeValue('email') === $newEmail) {
            return;
        }

        $notification = app(VerifyEmailChange::class);
        $notification->url = Filament::getVerifyEmailChangeUrl($record, $newEmail);

        $verificationSignature = Query::new($notification->url)->get('signature');
        cache()->put($verificationSignature, true, now()->addHour());

        $record->notify(app(NoticeOfEmailChangeRequest::class, [
            'blockVerificationUrl' => Filament::getBlockEmailChangeVerificationUrl($record, $newEmail, $verificationSignature),
            'newEmail' => $newEmail,
        ]));

        Notification::route('mail', $newEmail)->notify($notification);

        $this->getEmailChangeVerificationSentNotification($newEmail)?->send();

        $this->data['email'] = $record->getAttributeValue('email');
    }

    protected function getSavedNotification(): ?FilamentNotification
    {
        return FilamentNotification::make()
            ->success()
            ->title(__('filament-panels::auth/pages/edit-profile.notifications.saved.title'));
    }

    protected function getEmailChangeVerificationSentNotification(string $newEmail): ?FilamentNotification
    {
        return FilamentNotification::make()
            ->success()
            ->title(__('filament-panels::auth/pages/edit-profile.notifications.email_change_verification_sent.title', ['email' => $newEmail]))
            ->body(__('filament-panels::auth/pages/edit-profile.notifications.email_change_verification_sent.body', ['email' => $newEmail]));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            SchemaForm::make()
                ->schema([
                    Section::make('Personal Details')
                        ->description('Update your personal information.')
                        ->schema([
                            TextInput::make('first_name')
                                ->label('First name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label('Last name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Phone')
                                ->tel()
                                ->maxLength(50),
                        ])->columns(3),

                    Section::make('Security Information')
                        ->description('Manage your email, password, and two-factor authentication.')
                        ->schema([
                            $this->getEmailFormComponent(),
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                            $this->getCurrentPasswordFormComponent(),
                        ])->columns(2),

                    ...array_filter([$this->getMultiFactorAuthenticationContentComponent()]),
                ])
                ->livewireSubmitHandler('save')
                ->footer([
                    \Filament\Schemas\Components\Actions::make([
                        $this->getSaveFormAction(),
                        $this->getCancelFormAction(),
                    ])->sticky(),
                ]),
        ]);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::auth/pages/edit-profile.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    protected function getCancelFormAction(): Action
    {
        $url = filament()->getUrl();

        return Action::make('back')
            ->label(__('filament-panels::auth/pages/edit-profile.actions.cancel.label'))
            ->color('gray')
            ->alpineClickHandler(
                FilamentView::hasSpaMode($url)
                    ? 'document.referrer ? window.history.back() : Livewire.navigate('.Js::from($url).')'
                    : 'document.referrer ? window.history.back() : (window.location.href = '.Js::from($url).')',
            );
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true)
            ->live(debounce: 500);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password.validation_attribute'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.validation_attribute'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('filament-panels::auth/pages/edit-profile.form.current_password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.current_password.validation_attribute'))
            ->belowContent(__('filament-panels::auth/pages/edit-profile.form.current_password.below_content'))
            ->password()
            ->currentPassword(guard: Filament::getAuthGuard())
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(
                fn (Get $get): bool => filled($get('password')) ||
                    ($get('email') !== $this->getUser()->getAttributeValue('email'))
            )
            ->dehydrated(false);
    }

    protected function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        if (! Filament::hasMultiFactorAuthentication()) {
            return null;
        }

        $user = $this->getUser();

        return Section::make()
            ->label(__('filament-panels::auth/pages/edit-profile.multi_factor_authentication.label'))
            ->compact()
            ->divided()
            ->secondary()
            ->schema(
                collect(Filament::getMultiFactorAuthenticationProviders())
                    ->sort(fn (MultiFactorAuthenticationProvider $p): int => $p->isEnabled($user) ? 0 : 1)
                    ->map(
                        fn (MultiFactorAuthenticationProvider $p): Component => Group::make($p->getManagementSchemaComponents())->statePath($p->getId())
                    )
                    ->all()
            );
    }
}
