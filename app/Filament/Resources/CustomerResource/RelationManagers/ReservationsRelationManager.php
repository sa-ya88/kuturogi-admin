<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Reservation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'reservations';

    protected static ?string $title = '予約履歴';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('room.name')->label('客室'),
                Tables\Columns\TextColumn::make('plan.name')->label('プラン'),
                Tables\Columns\TextColumn::make('checkin_date')
                    ->label('開始日')
                    ->date('Y年m月d日'),
                Tables\Columns\TextColumn::make('checkout_date')
                    ->label('終了日')
                    ->date('Y年m月d日'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('合計')
                    ->money('jpy'),
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
            ])
            ->defaultSort('checkin_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('予約状況')
                    ->options([
                        Reservation::STATUS_CONFIRMED => '確定',
                        Reservation::STATUS_CANCELLED => 'キャンセル',
                        Reservation::STATUS_PENDING => '保留',
                    ]),
            ]);
    }
}
