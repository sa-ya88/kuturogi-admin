<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use App\Models\Reservation;
use App\Models\ReservationStay;
use App\Models\RoomUnit;
use App\Services\ReservationStayService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StaysRelationManager extends RelationManager
{
    protected static string $relationship = 'stays';

    protected static ?string $title = '滞在・部屋割';

    protected static ?string $modelLabel = '滞在行';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('representative_name')
                ->label('代表者名')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('room_unit_id')
                ->label('個別客室')
                ->options(function (?ReservationStay $record): array {
                    if (! $record) {
                        return [];
                    }

                    $service = app(ReservationStayService::class);
                    $options = $service->assignableUnitsForStay($record)
                        ->mapWithKeys(fn (RoomUnit $unit) => [$unit->id => $unit->displayLabel()])
                        ->all();

                    if ($record->room_unit_id && $record->roomUnit) {
                        $options[$record->room_unit_id] = $record->roomUnit->displayLabel();
                    }

                    return $options;
                })
                ->searchable()
                ->native(false)
                ->nullable()
                ->helperText('同タイプ・期間重複なしの部屋のみ選択できます'),
            Forms\Components\TextInput::make('sort_order')
                ->label('室順')
                ->numeric()
                ->disabled(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('representative_name')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('室'),
                Tables\Columns\TextColumn::make('representative_name')->label('代表者名'),
                Tables\Columns\TextColumn::make('roomUnit.code')
                    ->label('部屋')
                    ->placeholder('未割当')
                    ->description(fn (ReservationStay $record) => $record->roomUnit?->room?->name),
                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('チェックイン')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('checked_out_at')
                    ->label('チェックアウト')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('部屋割')
                    ->mutateFormDataUsing(function (array $data, ReservationStay $record): array {
                        return $data;
                    })
                    ->using(function (ReservationStay $record, array $data): ReservationStay {
                        $service = app(ReservationStayService::class);
                        $record->update([
                            'representative_name' => $data['representative_name'],
                        ]);

                        $unitId = $data['room_unit_id'] ?? null;

                        try {
                            $service->assignUnit(
                                $record->fresh(),
                                filled($unitId) ? (int) $unitId : null,
                            );
                        } catch (\Illuminate\Validation\ValidationException $e) {
                            Notification::make()
                                ->title('部屋を割り当てできません')
                                ->body(collect($e->errors())->flatten()->first() ?: $e->getMessage())
                                ->danger()
                                ->send();

                            throw $e;
                        }

                        return $record->fresh(['roomUnit']);
                    }),
                Tables\Actions\Action::make('checkInStay')
                    ->label('チェックイン')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ReservationStay $record): bool => $record->canCheckIn()
                        && $record->reservation?->status !== Reservation::STATUS_CANCELLED)
                    ->action(function (ReservationStay $record): void {
                        try {
                            app(ReservationStayService::class)->checkIn($record);
                            Notification::make()->title('チェックインしました')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('チェックイン失敗')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('checkOutStay')
                    ->label('チェックアウト')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ReservationStay $record): bool => $record->canCheckOut())
                    ->action(function (ReservationStay $record): void {
                        try {
                            app(ReservationStayService::class)->checkOut($record);
                            Notification::make()->title('チェックアウトしました')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('チェックアウト失敗')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('sort_order');
    }
}
