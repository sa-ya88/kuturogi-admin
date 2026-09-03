<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminOnly;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\DemoMode;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    use AuthorizesAdminOnly;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = '設定';

    protected static ?string $navigationLabel = 'ユーザー';

    protected static ?string $modelLabel = 'ユーザー';

    protected static ?string $pluralModelLabel = 'ユーザー';

    protected static ?int $navigationSort = 101;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('アカウント情報')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('氏名')
                    ->required()
                    ->maxLength(FieldLimits::PERSON_NAME),
                Forms\Components\TextInput::make('login_id')
                    ->label('ログインID')
                    ->required()
                    ->length(7)
                    ->rule('regex:/^k\d{6}$/')
                    ->unique(ignoreRecord: true)
                    ->default(fn (): string => User::generateLoginId())
                    ->helperText('形式: k + 6桁の数字（例: k123456）')
                    ->dehydrated(),
                Forms\Components\TextInput::make('email')
                    ->label('メールアドレス')
                    ->email()
                    ->required()
                    ->maxLength(FieldLimits::EMAIL)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('password')
                    ->label('パスワード')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(FieldLimits::PASSWORD)
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? '変更する場合のみ入力してください'
                        : null),
                Forms\Components\Select::make('role')
                    ->label('権限')
                    ->options([
                        User::ROLE_ADMIN => '管理者',
                        User::ROLE_STAFF => '一般',
                    ])
                    ->required()
                    ->default(User::ROLE_STAFF)
                    ->native(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('氏名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('login_id')
                    ->label('ログインID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('権限')
                    ->badge()
                    ->formatStateUsing(fn (User $record): string => $record->roleLabel())
                    ->color(fn (User $record): string => $record->isAdmin() ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        if (! DemoMode::allowsDeletes()) {
            return false;
        }

        if (! auth()->user()?->isAdmin()) {
            return false;
        }

        if (! $record instanceof User) {
            return false;
        }

        if ($record->is(auth()->user())) {
            return false;
        }

        if ($record->isDemoGuest()) {
            return false;
        }

        if ($record->isAdmin() && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            return false;
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return DemoMode::allowsDeletes()
            && (auth()->user()?->isAdmin() ?? false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
