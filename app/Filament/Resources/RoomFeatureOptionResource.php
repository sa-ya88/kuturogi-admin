<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminOnly;
use App\Filament\Resources\RoomFeatureOptionResource\Pages;
use App\Models\RoomFeatureOption;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;

class RoomFeatureOptionResource extends Resource
{
    use AuthorizesAdminOnly;

    protected static ?string $model = RoomFeatureOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = '設定';

    protected static ?string $navigationLabel = 'アピールポイント';

    protected static ?string $modelLabel = 'アピールポイント';

    protected static ?string $pluralModelLabel = 'アピールポイント';

    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('名称')
                ->required()
                ->maxLength(FieldLimits::OPTION_NAME)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('sort_order')
                ->label('表示順')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->maxValue(FieldLimits::SORT)
                ->step(1)
                ->default(fn () => (int) RoomFeatureOption::max('sort_order') + 1),
            Forms\Components\Toggle::make('is_active')
                ->label('有効')
                ->default(true)
                ->helperText('無効にすると客室のアピールポイント選択肢に表示されません'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (RoomFeatureOption $record): void {
                        if ($record->isUsedByRooms()) {
                            Notification::make()
                                ->title('削除できません')
                                ->body('このアピールポイントは客室で使用中です。無効化してください。')
                                ->danger()
                                ->send();

                            throw new Halt;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomFeatureOptions::route('/'),
            'create' => Pages\CreateRoomFeatureOption::route('/create'),
            'edit' => Pages\EditRoomFeatureOption::route('/{record}/edit'),
        ];
    }
}
