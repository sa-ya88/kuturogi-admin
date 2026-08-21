<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\FieldLimits;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getUserFormComponent(),
                        $this->getPasswordFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getUserFormComponent(): Select
    {
        return Select::make('login_id')
            ->label('氏名')
            ->options(fn (): array => User::query()
                ->orderBy('name')
                ->orderBy('login_id')
                ->get(['name', 'login_id'])
                ->mapWithKeys(fn (User $user): array => [
                    $user->login_id => "{$user->name}（{$user->login_id}）",
                ])
                ->all())
            ->searchable()
            ->preload()
            ->required()
            ->native(false)
            ->placeholder('ユーザーを選択')
            ->autofocus();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('パスワード')
            ->password()
            ->maxLength(FieldLimits::PASSWORD)
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        $user = User::query()
            ->where('login_id', $data['login_id'] ?? null)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->first();

        if (
            ! $user
            || blank($user->login_id)
            || ! Hash::check($data['password'] ?? '', $user->password)
        ) {
            $this->addAuthenticationError();

            return null;
        }

        if (
            $user instanceof FilamentUser
            && ! $user->canAccessPanel(Filament::getCurrentPanel())
        ) {
            $this->addAuthenticationError();

            return null;
        }

        Filament::auth()->login($user);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function addAuthenticationError(): void
    {
        $this->addError('data.password', '氏名またはパスワードが正しくありません。');
    }

    public function getTitle(): string|Htmlable
    {
        return 'ログイン';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable
    {
        $name = e((string) config('app.name'));
        $src = e(asset('images/login-title.webp'));

        return new HtmlString(
            <<<HTML
            <img
                src="{$src}"
                alt="{$name}"
                class="mx-auto w-full max-w-md rounded-lg"
            />
            HTML
        );
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('ログイン')
            ->action('authenticate');
    }
}
