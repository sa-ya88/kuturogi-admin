<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesStaffReadOnlyMutations;
use App\Filament\Resources\RoomUnitResource\Pages;
use App\Models\Room;
use App\Models\RoomUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class RoomUnitResource extends Resource
{
    use AuthorizesStaffReadOnlyMutations;

    protected static ?string $model = RoomUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = '客室管理';

    protected static ?string $modelLabel = '客室管理';

    protected static ?string $pluralModelLabel = '客室管理';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('room_id')
                ->label('客室タイプ')
                ->relationship('room', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->selectablePlaceholder(false),
            Forms\Components\TextInput::make('code')
                ->label('部屋番号')
                ->required()
                ->maxLength(50)
                ->helperText('例: 201')
                ->rules([
                    fn (Get $get, ?RoomUnit $record): \Illuminate\Validation\Rules\Unique => Rule::unique('room_units', 'code')
                        ->where('room_id', $get('room_id'))
                        ->ignore($record),
                ]),
            Forms\Components\TextInput::make('sort_order')
                ->label('表示順')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->default(fn () => (int) RoomUnit::max('sort_order') + 1),
            Forms\Components\Select::make('operation_status')
                ->label('運用状態')
                ->options(RoomUnit::operationStatusOptions())
                ->required()
                ->default(RoomUnit::OPERATION_IN_SERVICE)
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText('稼働中の室数が客室タイプの在庫になります'),
            Forms\Components\Select::make('current_status')
                ->label('ステータス')
                ->options(RoomUnit::currentStatusOptions())
                ->required()
                ->default(RoomUnit::CURRENT_BOOKABLE)
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText('ハウスキーピング用の現在値。一覧の日付別ステータスは占有状況から算出されます'),
            Forms\Components\Placeholder::make('plans_display')
                ->label('対象プラン')
                ->content(function (?RoomUnit $record): string {
                    if (! $record) {
                        return '保存後、プラン管理画面で設定した内容が表示されます';
                    }

                    $names = $record->effectivePlanNames();

                    return $names->isEmpty()
                        ? '未設定（プラン管理画面で設定）'
                        : $names->implode('、');
                })
                ->columnSpanFull()
                ->helperText('客室タイプに紐づくプランと、個別客室に紐づくプランの両方を表示します（設定はプラン管理画面）'),
            Forms\Components\Textarea::make('notes')
                ->label('備考')
                ->rows(4)
                ->columnSpanFull()
                ->helperText('少し狭い、訳あり など'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('順')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('部屋番号')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('room.name')
                    ->label('客室タイプ')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('operation_status')
                    ->label('運用状態')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RoomUnit::operationStatusOptions()[$state] ?? (string) $state)
                    ->color(fn (RoomUnit $record): string => $record->operationStatusColor()),
                Tables\Columns\TextColumn::make('day_status')
                    ->label('ステータス')
                    ->badge()
                    ->state(function (RoomUnit $record, mixed $livewire): string {
                        $date = $livewire instanceof Pages\ListRoomUnits
                            ? $livewire->resolveViewDate()
                            : now()->toDateString();

                        return $record->dayStatusForDate($date);
                    })
                    ->formatStateUsing(fn (?string $state): string => RoomUnit::dayStatusOptions()[$state] ?? (string) $state)
                    ->color(function (RoomUnit $record, mixed $livewire): string {
                        $date = $livewire instanceof Pages\ListRoomUnits
                            ? $livewire->resolveViewDate()
                            : now()->toDateString();

                        return $record->dayStatusColor($date);
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('備考')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\Filter::make('view_date')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->label('表示日')
                            ->displayFormat('Y/m/d')
                            ->native(false)
                            ->default(now())
                            ->required()
                            ->live(),
                    ])
                    ->query(fn (Builder $query): Builder => $query)
                    ->default([
                        'date' => now()->toDateString(),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (blank($data['date'] ?? null)) {
                            return null;
                        }

                        return '表示日: '.Carbon::parse($data['date'])->format('Y/m/d');
                    }),
                Tables\Filters\SelectFilter::make('room_id')
                    ->label('客室タイプ')
                    ->options(fn () => Room::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('operation_status')
                    ->label('運用状態')
                    ->options(RoomUnit::operationStatusOptions()),
                Tables\Filters\SelectFilter::make('day_status')
                    ->label('ステータス')
                    ->options(RoomUnit::dayStatusOptions())
                    ->query(fn (Builder $query): Builder => $query),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\EditAction::make()->label('個別設定'),
            ])
            ->defaultSort('sort_order');
    }

    /**
     * 表示日時点のステータスで絞り込む。
     */
    public static function constrainByDayStatus(Builder $query, string $status, string $date): Builder
    {
        $isToday = $date === now()->toDateString();

        $availableBase = fn (Builder $inner): Builder => $inner
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->where('current_status', '!=', RoomUnit::CURRENT_UNAVAILABLE);

        return match ($status) {
            RoomUnit::CURRENT_UNAVAILABLE => $query->where(function (Builder $inner): void {
                $inner->where('operation_status', RoomUnit::OPERATION_OUT_OF_SERVICE)
                    ->orWhere('current_status', RoomUnit::CURRENT_UNAVAILABLE);
            }),
            RoomUnit::CURRENT_AWAITING_ARRIVAL => $isToday
                ? $availableBase($query)
                    ->where('current_status', RoomUnit::CURRENT_AWAITING_ARRIVAL)
                    ->whereHas('dateOccupancies', fn (Builder $occupancy) => $occupancy->whereDate('date', $date))
                : $query->whereRaw('0 = 1'),
            RoomUnit::CURRENT_IN_HOUSE => $isToday
                ? $availableBase($query)
                    ->where('current_status', RoomUnit::CURRENT_IN_HOUSE)
                    ->whereHas('dateOccupancies', fn (Builder $occupancy) => $occupancy->whereDate('date', $date))
                : $query->whereRaw('0 = 1'),
            RoomUnit::CURRENT_NEEDS_CLEANING => $isToday
                ? $availableBase($query)
                    ->where('current_status', RoomUnit::CURRENT_NEEDS_CLEANING)
                    ->whereDoesntHave('dateOccupancies', fn (Builder $occupancy) => $occupancy->whereDate('date', $date))
                : $query->whereRaw('0 = 1'),
            RoomUnit::DAY_RESERVED => $availableBase($query)
                ->whereHas('dateOccupancies', fn (Builder $occupancy) => $occupancy->whereDate('date', $date))
                ->when(
                    $isToday,
                    fn (Builder $inner) => $inner->whereNotIn('current_status', [
                        RoomUnit::CURRENT_AWAITING_ARRIVAL,
                        RoomUnit::CURRENT_IN_HOUSE,
                    ])
                ),
            RoomUnit::CURRENT_BOOKABLE => $availableBase($query)
                ->whereDoesntHave('dateOccupancies', fn (Builder $occupancy) => $occupancy->whereDate('date', $date))
                ->when(
                    $isToday,
                    fn (Builder $inner) => $inner->where('current_status', '!=', RoomUnit::CURRENT_NEEDS_CLEANING)
                ),
            default => $query,
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['room.plans', 'plans']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomUnits::route('/'),
            'create' => Pages\CreateRoomUnit::route('/create'),
            'edit' => Pages\EditRoomUnit::route('/{record}/edit'),
        ];
    }
}
