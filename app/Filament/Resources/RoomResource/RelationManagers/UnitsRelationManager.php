<?php

namespace App\Filament\Resources\RoomResource\RelationManagers;

use App\Models\RoomUnit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = '客室管理';

    protected static ?string $modelLabel = '客室管理';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->label('部屋番号')
                ->required()
                ->maxLength(50),
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
                ->helperText('ハウスキーピング用の現在値'),
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
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $today = now()->toDateString();

                return $query->with([
                    'dateOccupancies' => fn ($occupancyQuery) => $occupancyQuery->whereDate('date', $today),
                ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('部屋番号'),
                Tables\Columns\TextColumn::make('operation_status')
                    ->label('運用状態')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RoomUnit::operationStatusOptions()[$state] ?? (string) $state)
                    ->color(fn (RoomUnit $record): string => $record->operationStatusColor()),
                Tables\Columns\TextColumn::make('day_status')
                    ->label('ステータス')
                    ->badge()
                    ->state(fn (RoomUnit $record): string => $record->dayStatusForDate(now()))
                    ->formatStateUsing(fn (?string $state): string => RoomUnit::dayStatusOptions()[$state] ?? (string) $state)
                    ->color(fn (RoomUnit $record): string => $record->dayStatusColor(now())),
                Tables\Columns\TextColumn::make('notes')->label('備考')->limit(24)->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('個別客室を追加'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('編集'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }
}
