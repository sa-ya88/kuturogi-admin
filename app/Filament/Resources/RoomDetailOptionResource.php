<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminOnly;
use App\Filament\Resources\RoomDetailOptionResource\Pages;
use App\Models\RoomDetailOption;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class RoomDetailOptionResource extends Resource
{
    use AuthorizesAdminOnly;

    protected static ?string $model = RoomDetailOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = '設定';

    protected static ?string $navigationLabel = '設備・アメニティ';

    protected static ?string $modelLabel = '設備・アメニティ';

    protected static ?string $pluralModelLabel = '設備・アメニティ';

    protected static ?int $navigationSort = 101;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category')
                ->label('種別')
                ->options(RoomDetailOption::categoryOptions())
                ->required()
                ->native(false)
                ->live(),
            Forms\Components\TextInput::make('name')
                ->label('名称')
                ->required()
                ->maxLength(FieldLimits::OPTION_NAME)
                ->rules([
                    fn (Get $get, ?RoomDetailOption $record): Unique => Rule::unique('room_detail_options', 'name')
                        ->where('category', $get('category'))
                        ->ignore($record),
                ]),
            Forms\Components\TextInput::make('sort_order')
                ->label('表示順')
                ->numeric()
                ->integer()
                ->required()
                ->minValue(1)
                ->maxValue(FieldLimits::SORT)
                ->step(1)
                ->default(function (Get $get): int {
                    $query = RoomDetailOption::query();

                    if (filled($get('category'))) {
                        $query->where('category', $get('category'));
                    }

                    return (int) $query->max('sort_order') + 1;
                }),
            Forms\Components\Toggle::make('is_active')
                ->label('有効')
                ->default(true)
                ->helperText('無効にすると客室タイプ編集の選択肢に表示されません'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('種別')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => RoomDetailOption::categoryOptions()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === RoomDetailOption::CATEGORY_AMENITY ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('name')
                    ->label('名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('有効')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('種別')
                    ->options(RoomDetailOption::categoryOptions()),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (RoomDetailOption $record): void {
                        if ($record->isUsedByRooms()) {
                            Notification::make()
                                ->title('削除できません')
                                ->body('この項目は客室で使用中です。無効化してください。')
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
            'index' => Pages\ListRoomDetailOptions::route('/'),
            'create' => Pages\CreateRoomDetailOption::route('/create'),
            'edit' => Pages\EditRoomDetailOption::route('/{record}/edit'),
        ];
    }
}
