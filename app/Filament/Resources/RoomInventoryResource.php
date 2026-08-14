<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoomInventoryResource\Pages;
use App\Models\RoomInventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RoomInventoryResource extends Resource
{
    protected static ?string $model = RoomInventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = '在庫管理';

    protected static ?string $modelLabel = '在庫';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('room_id')
                ->label('客室')
                ->relationship('room', 'name')
                ->required(),
            Forms\Components\DatePicker::make('date')
                ->label('日付')
                ->required(),
            Forms\Components\TextInput::make('remains')
                ->label('在庫数')
                ->numeric()
                ->minValue(0)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.name')->label('客室'),
                Tables\Columns\TextColumn::make('date')->label('日付')->date('Y年m月d日'),
                Tables\Columns\TextColumn::make('remains')->label('残室数'),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomInventories::route('/'),
            'create' => Pages\CreateRoomInventory::route('/create'),
            'edit' => Pages\EditRoomInventory::route('/{record}/edit'),
        ];
    }
}
