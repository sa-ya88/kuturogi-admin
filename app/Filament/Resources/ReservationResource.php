<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\DemoPersonalDataNotice;
use App\Filament\Resources\ReservationResource\Pages;
use App\Filament\Resources\ReservationResource\RelationManagers;
use App\Models\Reservation;
use App\Support\DemoMode;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = '予約管理';

    protected static ?string $modelLabel = '予約';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            DemoPersonalDataNotice::make(),
            Forms\Components\Select::make('room_id')
                ->label('客室タイプ')
                ->relationship('room', 'name')
                ->required()
                ->preload()
                ->native(false)
                ->selectablePlaceholder(false),
            Forms\Components\Select::make('plan_id')
                ->label('プラン')
                ->relationship('plan', 'name')
                ->required()
                ->preload()
                ->native(false)
                ->selectablePlaceholder(false),
            Forms\Components\Select::make('customer_id')
                ->label('顧客')
                ->relationship('customer', 'name')
                ->required()
                ->searchable()
                ->native(false)
                ->selectablePlaceholder(false),
            Forms\Components\DatePicker::make('checkin_date')
                ->label('開始日')
                ->required()
                ->native(false)
                ->displayFormat('Y年m月d日'),
            Forms\Components\DatePicker::make('checkout_date')
                ->label('終了日')
                ->required()
                ->native(false)
                ->displayFormat('Y年m月d日'),
            Forms\Components\TextInput::make('guest_count')
                ->label('人数')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->maxValue(FieldLimits::COUNT)
                ->default(2),
            Forms\Components\TextInput::make('room_count')
                ->label('室数')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->maxValue(FieldLimits::COUNT)
                ->default(1),
            Forms\Components\TextInput::make('adult_count')
                ->label('大人')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(0)
                ->maxValue(FieldLimits::COUNT)
                ->default(2),
            Forms\Components\TextInput::make('child_count')
                ->label('子供')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(FieldLimits::COUNT)
                ->default(0),
            Forms\Components\TextInput::make('total_price')
                ->label('合計')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(0)
                ->maxValue(FieldLimits::PRICE),
            Forms\Components\Select::make('status')
                ->label('予約状況')
                ->options([
                    Reservation::STATUS_PENDING => '保留',
                    Reservation::STATUS_CONFIRMED => '確定',
                    Reservation::STATUS_CANCELLED => 'キャンセル',
                ])
                ->required()
                ->default(Reservation::STATUS_CONFIRMED)
                ->native(false)
                ->selectablePlaceholder(false),
            Forms\Components\TextInput::make('guest_email')
                ->label('メールアドレス')
                ->email()
                ->maxLength(FieldLimits::EMAIL)
                ->placeholder(fn (): ?string => DemoMode::enabled() ? DemoMode::dummyEmail() : null)
                ->helperText(fn (): ?string => DemoMode::enabled()
                    ? '実在のメールアドレスは入力しないでください'
                    : null),
            Forms\Components\TextInput::make('guest_tel')
                ->label('電話')
                ->maxLength(FieldLimits::TEL)
                ->placeholder(fn (): ?string => DemoMode::enabled() ? DemoMode::dummyTel() : null),
            Forms\Components\Select::make('payment_method')
                ->label('支払い方法')
                ->options(['local' => '現地払い', 'credit' => 'クレジット'])
                ->default('local')
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText(fn (): ?string => DemoMode::enabled()
                    ? 'クレジットは Stripe テストモードのみ。カード番号は '.DemoMode::stripeTestCard()
                    : null),
            Forms\Components\Placeholder::make('payment_status_display')
                ->label('決済状況')
                ->content(fn (?Reservation $record): string => Reservation::paymentStatusLabel($record?->payment_status)
                    .((bool) $record?->cancel_fee_uncollected ? '（違約金未収）' : '')),
            Forms\Components\Placeholder::make('selected_choices_display')
                ->label('プランの選択項目')
                ->content(function (?Reservation $record): string {
                    if (! $record || blank($record->selected_choices)) {
                        return '—';
                    }

                    return collect($record->selected_choices)
                        ->map(fn (array $item): string => ($item['prompt'] ?? '').': '.($item['choice'] ?? ''))
                        ->implode("\n");
                })
                ->visible(fn (?Reservation $record): bool => filled($record?->selected_choices))
                ->columnSpanFull(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['room', 'plan', 'stays.roomUnit.room']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StaysRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kuturogi_reservation_id')
                    ->label('予約番号')
                    ->state(fn (Reservation $record): string => $record->numberForDisplay())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $normalized = ltrim($search, '#');

                        return $query->where(function (Builder $inner) use ($normalized): void {
                            $inner
                                ->where('kuturogi_reservation_id', $normalized)
                                ->orWhere('id', $normalized);
                        });
                    })
                    ->sortable()
                    ->tooltip('ゲストサイトの予約確認に表示される番号'),
                Tables\Columns\TextColumn::make('guest_name')->label('ゲスト名')->searchable(),
                Tables\Columns\TextColumn::make('room.name')->label('客室タイプ'),
                Tables\Columns\TextColumn::make('assigned_units')
                    ->label('部屋')
                    ->state(function (Reservation $record): string {
                        $codes = $record->stays
                            ->pluck('roomUnit.code')
                            ->filter()
                            ->values();

                        return $codes->isEmpty() ? '未割当' : $codes->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('plan.name')->label('プラン'),
                Tables\Columns\TextColumn::make('checkin_date')->label('開始日')->date('Y年m月d日')->sortable(),
                Tables\Columns\TextColumn::make('plan.checkin_time')
                    ->label('CI時刻')
                    ->state(fn (Reservation $record): string => $record->plan?->effectiveCheckinTime() ?? '15:00'),
                Tables\Columns\TextColumn::make('checkout_date')->label('終了日')->date('Y年m月d日'),
                Tables\Columns\TextColumn::make('plan.checkout_time')
                    ->label('CO時刻')
                    ->state(fn (Reservation $record): string => $record->plan?->effectiveCheckoutTime() ?? '11:00'),
                Tables\Columns\TextColumn::make('total_price')->label('合計')->money('jpy'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('決済')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Reservation $record): string => Reservation::paymentStatusLabel($state)
                        .($record->cancel_fee_uncollected ? '・未収' : ''))
                    ->color(fn (?string $state, Reservation $record): string => $record->cancel_fee_uncollected
                        ? 'danger'
                        : Reservation::paymentStatusColor($state)),
                Tables\Columns\TextColumn::make('status')
                    ->label('予約状況')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Reservation::STATUS_PENDING => '保留',
                        Reservation::STATUS_CONFIRMED => '確定',
                        Reservation::STATUS_CANCELLED => 'キャンセル',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Reservation::STATUS_PENDING => 'warning',
                        Reservation::STATUS_CONFIRMED => 'success',
                        Reservation::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('stay_status')
                    ->label('滞在状況')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Reservation::stayStatusLabel($state ?? Reservation::STAY_STATUS_RESERVED))
                    ->color(fn (?string $state): string => match ($state) {
                        Reservation::STAY_STATUS_IN_HOUSE => 'success',
                        Reservation::STAY_STATUS_PARTIALLY_IN_HOUSE => 'warning',
                        Reservation::STAY_STATUS_CHECKED_OUT => 'gray',
                        default => 'info',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('予約状況')
                    ->options([
                        Reservation::STATUS_CONFIRMED => '確定',
                        Reservation::STATUS_CANCELLED => 'キャンセル',
                        Reservation::STATUS_PENDING => '保留',
                    ]),
                Tables\Filters\SelectFilter::make('stay_status')
                    ->label('滞在状況')
                    ->options([
                        Reservation::STAY_STATUS_RESERVED => '予約',
                        Reservation::STAY_STATUS_PARTIALLY_IN_HOUSE => '一部滞在中',
                        Reservation::STAY_STATUS_IN_HOUSE => '滞在中',
                        Reservation::STAY_STATUS_CHECKED_OUT => 'チェックアウト済',
                    ]),
                Tables\Filters\Filter::make('checkin_today')
                    ->label('本日チェックイン')
                    ->query(fn (Builder $query) => $query
                        ->where('status', '!=', Reservation::STATUS_CANCELLED)
                        ->whereDate('checkin_date', today())
                        ->whereHas('stays', fn (Builder $stayQuery) => $stayQuery->whereNull('checked_in_at'))),
                Tables\Filters\Filter::make('checkout_today')
                    ->label('本日チェックアウト')
                    ->query(fn (Builder $query) => $query
                        ->where('status', '!=', Reservation::STATUS_CANCELLED)
                        ->whereDate('checkout_date', today())
                        ->whereHas('stays', fn (Builder $stayQuery) => $stayQuery
                            ->whereNotNull('checked_in_at')
                            ->whereNull('checked_out_at'))),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('編集'),
            ])
            ->defaultSort('checkin_date', 'desc');
    }

    public static function canDelete(Model $record): bool
    {
        if (! DemoMode::allowsDeletes()) {
            return false;
        }

        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        if (! DemoMode::allowsDeletes()) {
            return false;
        }

        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ReservationCalendar::route('/'),
            'list' => Pages\ListReservations::route('/list'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
