<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesRecordResource\Pages;
use App\Models\SalesRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesRecordResource extends Resource
{
    protected static ?string $model = SalesRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = '売上・経理';

    protected static ?string $modelLabel = '売上';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('reservation.plan');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('reservation_id')
                ->relationship('reservation', 'id')
                ->required(),
            Forms\Components\TextInput::make('amount')->numeric()->required(),
            Forms\Components\DateTimePicker::make('recorded_at')->required(),
            Forms\Components\Select::make('status')
                ->options([
                    SalesRecord::STATUS_RECORDED => '計上済',
                    SalesRecord::STATUS_CANCELLED => '取消',
                ])
                ->required(),
            Forms\Components\Textarea::make('notes'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reservation.guest_name')->label('予約者名'),
                Tables\Columns\TextColumn::make('reservation.plan.name')->label('プラン'),
                Tables\Columns\TextColumn::make('amount')->label('合計金額')->money('jpy'),
                Tables\Columns\TextColumn::make('recorded_at')->label('予約日')->date('Y年m月d日'),
                Tables\Columns\TextColumn::make('status')
                    ->label('記録状況')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        SalesRecord::STATUS_RECORDED => '計上済',
                        SalesRecord::STATUS_CANCELLED => '取消',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        SalesRecord::STATUS_RECORDED => 'success',
                        SalesRecord::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('recorded_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesRecords::route('/'),
        ];
    }
}
