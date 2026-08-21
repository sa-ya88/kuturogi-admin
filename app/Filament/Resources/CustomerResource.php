<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\DemoPersonalDataNotice;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use App\Support\DemoMode;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = '顧客管理';

    protected static ?string $modelLabel = '顧客';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('reservations')
            ->withSum(
                ['reservations as total_spent' => fn (Builder $q) => $q->where('status', 'confirmed')],
                'total_price'
            );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            DemoPersonalDataNotice::make(),
            Forms\Components\Section::make('基本情報')->schema([
                Forms\Components\Select::make('type')
                    ->label('種別')
                    ->options([
                        Customer::TYPE_MEMBER => '会員',
                        Customer::TYPE_GUEST => 'ゲスト',
                    ])
                    ->required()
                    ->default(Customer::TYPE_GUEST),
                Forms\Components\TextInput::make('name')
                    ->label('氏名')
                    ->required()
                    ->maxLength(FieldLimits::PERSON_NAME)
                    ->placeholder(fn (): ?string => DemoMode::enabled() ? DemoMode::dummyName() : null)
                    ->helperText(fn (): ?string => DemoMode::enabled()
                        ? '実在の氏名は入力しないでください'
                        : null),
                Forms\Components\TextInput::make('name_kana')
                    ->label('フリガナ')
                    ->maxLength(FieldLimits::KANA),
                Forms\Components\TextInput::make('email')
                    ->label('メールアドレス')
                    ->email()
                    ->maxLength(FieldLimits::EMAIL)
                    ->placeholder(fn (): ?string => DemoMode::enabled() ? DemoMode::dummyEmail() : null),
                Forms\Components\TextInput::make('tel')
                    ->label('電話')
                    ->maxLength(FieldLimits::TEL)
                    ->placeholder(fn (): ?string => DemoMode::enabled() ? DemoMode::dummyTel() : null),
                Forms\Components\DatePicker::make('birthday')->label('生年月日'),
                Forms\Components\Select::make('gender')
                    ->label('性別')
                    ->options(['male' => '男性', 'female' => '女性', 'other' => 'その他']),
                Forms\Components\TextInput::make('zip_code')
                    ->label('郵便番号')
                    ->maxLength(FieldLimits::ZIP),
                Forms\Components\TextInput::make('address')
                    ->label('住所')
                    ->maxLength(FieldLimits::ADDRESS),
            ])->columns(2),
            Forms\Components\Section::make('管理情報')->schema([
                Forms\Components\TagsInput::make('tags')
                    ->label('タグ')
                    ->placeholder('タグを追加')
                    ->nestedRecursiveRules(['max:'.FieldLimits::TAG])
                    ->helperText('「リピーター」「VIP」は予約データから自動付与されます'),
                Forms\Components\Textarea::make('notes')
                    ->label('メモ')
                    ->maxLength(FieldLimits::NOTES)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('氏名')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('種別')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'member' ? '会員' : 'ゲスト')
                    ->color(fn (string $state) => $state === 'member' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('email')->label('メールアドレス')->searchable(),
                Tables\Columns\TextColumn::make('tel')->label('電話'),
                Tables\Columns\TextColumn::make('reservations_count')->label('予約数')->sortable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('累計利用額')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_stayed_at')
                    ->label('最終宿泊')
                    ->date('Y年m月d日')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tags')
                    ->label('タグ')
                    ->badge()
                    ->separator(','),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('種別')
                    ->options([
                        Customer::TYPE_MEMBER => '会員',
                        Customer::TYPE_GUEST => 'ゲスト',
                    ]),
                Tables\Filters\Filter::make('repeaters')
                    ->label('リピーター')
                    ->query(fn (Builder $q) => $q->repeaters()),
                Tables\Filters\Filter::make('vip')
                    ->label('VIP（10万円以上）')
                    ->query(fn (Builder $q) => $q->vip()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('last_stayed_at', 'desc');
    }

    public static function canDelete(Model $record): bool
    {
        if (! \App\Support\DemoMode::allowsDeletes()) {
            return false;
        }

        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        if (! \App\Support\DemoMode::allowsDeletes()) {
            return false;
        }

        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ReservationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
