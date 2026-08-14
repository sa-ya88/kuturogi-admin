<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesStaffReadOnlyMutations;
use App\Filament\Resources\RoomResource\Pages;
use App\Filament\Resources\RoomResource\RelationManagers;
use App\Models\Room;
use App\Models\RoomFeatureOption;
use App\Services\KuturogiSyncService;
use App\Services\RoomSortOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Collection;

class RoomResource extends Resource
{
    use AuthorizesStaffReadOnlyMutations;

    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = '客室タイプ';

    protected static ?string $modelLabel = '客室タイプ';

    protected static ?int $navigationSort = 6;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('基本情報')
                ->description('保存すると kuturogi の「お部屋」一覧へ自動反映されます。')
                ->schema([
                    Forms\Components\TextInput::make('sort_order')
                        ->label('一覧表示順')
                        ->numeric()
                        ->integer()
                        ->required()
                        ->minValue(1)
                        ->step(1)
                        ->default(fn () => (int) Room::max('sort_order') + 1)
                        ->helperText('数値が小さいほど先頭に表示されます（整数のみ）'),
                    Forms\Components\TextInput::make('name')
                        ->label('客室名')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price_per_person')
                        ->label('1人あたり料金（円）')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                    Forms\Components\Placeholder::make('stock_count_display')
                        ->label('在庫数')
                        ->content(function (?Room $record): string {
                            if (! $record) {
                                return '0（個別客室の「稼働中」件数。客室管理で登録後に反映）';
                            }

                            $count = $record->inServiceUnitsCount();

                            return "{$count}（稼働中の個別客室数）";
                        })
                        ->helperText('客室タイプでは在庫を持ちません。稼働中の個別客室数が在庫になります'),
                    Forms\Components\DatePicker::make('available_from')
                        ->label('開始日')
                        ->required()
                        ->default(now())
                        ->native(false)
                        ->displayFormat('Y/m/d')
                        ->helperText('この日から予約可能（在庫を設定）'),
                    Forms\Components\DatePicker::make('available_to')
                        ->label('終了日')
                        ->native(false)
                        ->displayFormat('Y/m/d')
                        ->afterOrEqual('available_from')
                        ->helperText('任意。未入力の場合は期限なし（この日まで予約可能）'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('公開（お部屋一覧に表示）')
                        ->default(true)
                        ->helperText('OFF にすると kuturogi の一覧から非表示になります'),
                    Forms\Components\Textarea::make('description')
                        ->label('説明')
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make('詳細')
                ->description('保存すると在庫カレンダー・在庫管理の予約可能期間へ反映されます。在庫数は稼働中の個別客室数です。')
                ->schema([
                    Forms\Components\Select::make('features')
                        ->label('設備・特徴')
                        ->multiple()
                        ->options(fn (?Room $record): array => RoomFeatureOption::optionsForSelect($record))
                        ->searchable()
                        ->preload()
                        ->helperText('選択肢は「設定 → 設備・特徴」で管理できます')
                        ->rules([
                            'array',
                            function (): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (! is_array($value)) {
                                        return;
                                    }

                                    $allowed = RoomFeatureOption::query()
                                        ->active()
                                        ->pluck('name')
                                        ->all();

                                    foreach ($value as $feature) {
                                        if (! in_array($feature, $allowed, true)) {
                                            $fail('無効な設備・特徴が選択されています。設定画面で有効化するか、選択を見直してください。');
                                        }
                                    }
                                };
                            },
                        ]),
                    Forms\Components\FileUpload::make('images')
                        ->label('客室画像')
                        ->helperText('webp / png / jpeg（jpg）のみ。最大5枚。')
                        ->disk('kuturogi_images')
                        ->directory('.')
                        ->visibility('public')
                        ->image()
                        ->multiple()
                        ->maxFiles(5)
                        ->reorderable()
                        ->acceptedFileTypes([
                            'image/webp',
                            'image/png',
                            'image/jpeg',
                        ])
                        ->storeFiles(false)
                        ->columnSpanFull(),
                    Forms\Components\CheckboxList::make('plans')
                        ->label('紐付けプラン')
                        ->relationship(
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                        )
                        ->columns(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isAdmin = fn (): bool => auth()->user()?->isAdmin() ?? false;

        return $table
            ->columns([
                Tables\Columns\TextInputColumn::make('sort_order')
                    ->label('表示順')
                    ->type('number')
                    ->inputMode('numeric')
                    ->step(1)
                    ->rules(['required', 'integer', 'min:1'])
                    ->extraInputAttributes(['min' => '1', 'step' => '1'])
                    ->visible($isAdmin)
                    ->updateStateUsing(function (Room $record, $state): int {
                        $sortOrderService = app(RoomSortOrderService::class);
                        $position = $sortOrderService->parseIntegerSortOrder($state);
                        $sortOrderService->applySortOrder($record, $position);

                        try {
                            $sortOrderService->syncAllToKuturogi();

                            Notification::make()
                                ->title('並び順を kuturogi の HP に反映しました')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('並び順の kuturogi 反映に失敗しました')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }

                        return $record->fresh()->sort_order;
                    }),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('表示順')
                    ->sortable()
                    ->visible(fn (): bool => ! $isAdmin()),
                Tables\Columns\TextColumn::make('name')
                    ->label('客室名')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_person')
                    ->label('1人料金')
                    ->money('jpy')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_count')
                    ->label('在庫数')
                    ->sortable()
                    ->tooltip('稼働中の個別客室数'),
                Tables\Columns\TextColumn::make('plans_count')
                    ->counts('plans')
                    ->label('プラン数'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('公開')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('公開'),
            ])
            ->reorderable('sort_order', $isAdmin)
            ->recordUrl(fn (Room $record): string => static::getUrl(
                auth()->user()?->isAdmin() ? 'edit' : 'view',
                ['record' => $record],
            ))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('客室を削除し、kuturogi からも削除しました')
                    ->action(function (Room $record) {
                        try {
                            app(KuturogiSyncService::class)->deleteRoomWithSync($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('削除できません')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $syncService = app(KuturogiSyncService::class);

                            foreach ($records as $record) {
                                try {
                                    $syncService->deleteRoomWithSync($record);
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('削除できません')
                                        ->body("{$record->name}: {$e->getMessage()}")
                                        ->danger()
                                        ->send();

                                    throw new Halt();
                                }
                            }

                            Notification::make()
                                ->title('選択した客室を kuturogi からも削除しました')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('基本情報')->schema([
                Infolists\Components\TextEntry::make('sort_order')->label('一覧表示順'),
                Infolists\Components\TextEntry::make('name')->label('客室名'),
                Infolists\Components\TextEntry::make('price_per_person')
                    ->label('1人あたり料金')
                    ->money('jpy'),
                Infolists\Components\TextEntry::make('stock_count')
                    ->label('在庫数')
                    ->helperText('稼働中の個別客室数'),
                Infolists\Components\TextEntry::make('available_from')
                    ->label('開始日')
                    ->date('Y/m/d'),
                Infolists\Components\TextEntry::make('available_to')
                    ->label('終了日')
                    ->date('Y/m/d'),
                Infolists\Components\IconEntry::make('is_active')->label('公開')->boolean(),
                Infolists\Components\TextEntry::make('description')
                    ->label('説明')
                    ->columnSpanFull(),
            ])->columns(2),
            Infolists\Components\Section::make('詳細')->schema([
                Infolists\Components\TextEntry::make('features')
                    ->label('設備・特徴')
                    ->badge()
                    ->columnSpanFull(),
                Infolists\Components\ImageEntry::make('images')
                    ->label('客室画像')
                    ->disk('kuturogi_images')
                    ->columnSpanFull(),
                Infolists\Components\TextEntry::make('plans.name')
                    ->label('紐付けプラン')
                    ->badge()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UnitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'view' => Pages\ViewRoom::route('/{record}'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
