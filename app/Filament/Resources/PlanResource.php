<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesStaffReadOnlyMutations;
use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    use AuthorizesStaffReadOnlyMutations;

    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'プラン';

    protected static ?string $modelLabel = 'プラン';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('基本情報')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('プラン名')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('price_per_person')
                        ->label('1人料金')
                        ->numeric()
                        ->integer()
                        ->required()
                        ->minValue(0),
                    Forms\Components\Toggle::make('has_breakfast')
                        ->label('朝食付'),
                    Forms\Components\Toggle::make('has_dinner')
                        ->label('夕食付'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('公開')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('プラン概要')
                ->schema([
                    Forms\Components\Textarea::make('description')
                        ->label('プラン概要')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('対象客室')
                ->description('このプランの対象となる客室を選択してください（複数選択可）')
                ->schema([
                    Forms\Components\CheckboxList::make('rooms')
                        ->label('対象客室')
                        ->relationship(
                            name: 'rooms',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                        )
                        ->columns(2)
                        ->columnSpanFull()
                        ->live(),
                    Forms\Components\Select::make('roomUnits')
                        ->label('対象個別客室')
                        ->relationship(
                            name: 'roomUnits',
                            titleAttribute: 'code',
                            modifyQueryUsing: function ($query, Get $get) {
                                $roomIds = $get('rooms') ?? [];

                                $query
                                    ->with('room')
                                    ->orderBy('sort_order')
                                    ->orderBy('code');

                                if (is_array($roomIds) && $roomIds !== []) {
                                    $query->whereIn('room_id', $roomIds);
                                }

                                return $query;
                            },
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn ($record): string => $record->displayLabel()
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->columnSpanFull()
                        ->helperText('対象客室に紐づく個別客室（部屋番号）を選択できます'),
                ]),

            Forms\Components\Section::make('チェックイン＆チェックアウト指定')
                ->description('アーリーチェックイン、レイトチェックアウトなどのプラン作成時に使用します。どちらか一方、または両方を指定できます。')
                ->schema([
                    Forms\Components\Toggle::make('has_checkin_time')
                        ->label('チェックイン時刻を指定')
                        ->live(),
                    Forms\Components\TimePicker::make('checkin_time')
                        ->label('チェックイン時刻')
                        ->seconds(false)
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('has_checkin_time'))
                        ->required(fn (Get $get): bool => (bool) $get('has_checkin_time')),
                    Forms\Components\Toggle::make('has_checkout_time')
                        ->label('チェックアウト時刻を指定')
                        ->live(),
                    Forms\Components\TimePicker::make('checkout_time')
                        ->label('チェックアウト時刻')
                        ->seconds(false)
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('has_checkout_time'))
                        ->required(fn (Get $get): bool => (bool) $get('has_checkout_time')),
                ])
                ->columns(2),

            Forms\Components\Section::make('選択肢項目')
                ->description('予約時にゲストへ表示する選択項目を設定できます（例: 夕食のメイン料理）')
                ->schema([
                    Forms\Components\Repeater::make('choice_options')
                        ->label('選択肢項目')
                        ->schema([
                            Forms\Components\TextInput::make('prompt')
                                ->label('選択文')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('例: 夕食のメイン料理をお選びください')
                                ->columnSpanFull(),
                            Forms\Components\Repeater::make('choices')
                                ->label('選択肢')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('選択肢')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('例: ビーフ'),
                                ])
                                ->minItems(1)
                                ->defaultItems(2)
                                ->addActionLabel('選択肢を追加')
                                ->reorderable()
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('選択項目を追加')
                        ->collapsible()
                        ->collapsed()
                        ->default([])
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('早割設定')
                ->schema([
                    Forms\Components\Toggle::make('has_early_bird')
                        ->label('早割を設定')
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\Radio::make('early_bird_discount_type')
                        ->label('割引方法')
                        ->options([
                            Plan::DISCOUNT_TYPE_PERCENT => '割引率（％）',
                            Plan::DISCOUNT_TYPE_FIXED => '固定額（円）',
                        ])
                        ->inline()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('early_bird_discount_value', null))
                        ->visible(fn (Get $get): bool => (bool) $get('has_early_bird'))
                        ->required(fn (Get $get): bool => (bool) $get('has_early_bird'))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('early_bird_discount_value')
                        ->label('割引値')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): ?int => $get('early_bird_discount_type') === Plan::DISCOUNT_TYPE_PERCENT ? 100 : null)
                        ->suffix(fn (Get $get): ?string => match ($get('early_bird_discount_type')) {
                            Plan::DISCOUNT_TYPE_PERCENT => '％',
                            Plan::DISCOUNT_TYPE_FIXED => '円',
                            default => null,
                        })
                        ->visible(fn (Get $get): bool => (bool) $get('has_early_bird') && filled($get('early_bird_discount_type')))
                        ->required(fn (Get $get): bool => (bool) $get('has_early_bird')),
                    Forms\Components\TextInput::make('early_bird_days_before')
                        ->label('対象日数')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->suffix('日前')
                        ->helperText('宿泊日が予約日のこの日数以上前の場合に早割が適用されます（例: 30 → 30日以上前の予約）')
                        ->visible(fn (Get $get): bool => (bool) $get('has_early_bird'))
                        ->required(fn (Get $get): bool => (bool) $get('has_early_bird')),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('プラン名')->searchable(),
                Tables\Columns\TextColumn::make('price_per_person')->label('1人料金')->money('jpy'),
                Tables\Columns\IconColumn::make('has_breakfast')->boolean()->label('朝食'),
                Tables\Columns\IconColumn::make('has_dinner')->boolean()->label('夕食'),
                Tables\Columns\TextColumn::make('rooms_count')->counts('rooms')->label('客室数'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->recordUrl(fn (Plan $record): string => static::getUrl(
                auth()->user()?->isAdmin() ? 'edit' : 'view',
                ['record' => $record],
            ))
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('基本情報')->schema([
                Infolists\Components\TextEntry::make('name')->label('プラン名'),
                Infolists\Components\TextEntry::make('price_per_person')
                    ->label('1人料金')
                    ->money('jpy'),
                Infolists\Components\IconEntry::make('has_breakfast')->label('朝食付')->boolean(),
                Infolists\Components\IconEntry::make('has_dinner')->label('夕食付')->boolean(),
                Infolists\Components\IconEntry::make('is_active')->label('公開')->boolean(),
            ])->columns(2),
            Infolists\Components\Section::make('プラン概要')->schema([
                Infolists\Components\TextEntry::make('description')
                    ->label('プラン概要')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('対象客室')->schema([
                Infolists\Components\TextEntry::make('rooms.name')
                    ->label('対象客室')
                    ->badge()
                    ->placeholder('—')
                    ->columnSpanFull(),
                Infolists\Components\TextEntry::make('roomUnits')
                    ->label('対象個別客室')
                    ->state(fn (Plan $record): string => $record->roomUnits
                        ->loadMissing('room')
                        ->map(fn ($unit) => $unit->displayLabel())
                        ->implode('、') ?: '—')
                    ->columnSpanFull(),
            ]),
            Infolists\Components\Section::make('チェックイン＆チェックアウト指定')->schema([
                Infolists\Components\IconEntry::make('has_checkin_time')
                    ->label('チェックイン時刻を指定')
                    ->boolean(),
                Infolists\Components\TextEntry::make('checkin_time')
                    ->label('チェックイン時刻')
                    ->formatStateUsing(fn (Plan $record): ?string => $record->formattedCheckinTime())
                    ->placeholder('—'),
                Infolists\Components\IconEntry::make('has_checkout_time')
                    ->label('チェックアウト時刻を指定')
                    ->boolean(),
                Infolists\Components\TextEntry::make('checkout_time')
                    ->label('チェックアウト時刻')
                    ->formatStateUsing(fn (Plan $record): ?string => $record->formattedCheckoutTime())
                    ->placeholder('—'),
            ])->columns(2),
            Infolists\Components\Section::make('選択肢項目')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('choice_options')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('prompt')
                                ->label('選択文'),
                            Infolists\Components\TextEntry::make('choices')
                                ->label('選択肢')
                                ->formatStateUsing(fn (?array $state): string => collect($state ?? [])
                                    ->pluck('label')
                                    ->filter()
                                    ->implode(' / ') ?: '—'),
                        ])
                        ->columnSpanFull(),
                ])
                ->visible(fn (?Plan $record): bool => filled($record?->choice_options)),
            Infolists\Components\Section::make('早割設定')->schema([
                Infolists\Components\IconEntry::make('has_early_bird')
                    ->label('早割を設定')
                    ->boolean(),
                Infolists\Components\TextEntry::make('early_bird_discount_type')
                    ->label('割引方法')
                    ->formatStateUsing(fn (Plan $record): ?string => $record->earlyBirdDiscountTypeLabel())
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('early_bird_discount_value')
                    ->label('割引内容')
                    ->formatStateUsing(fn (Plan $record): ?string => $record->formattedEarlyBirdDiscount())
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('early_bird_days_before')
                    ->label('対象日数')
                    ->formatStateUsing(fn (?int $state): ?string => $state ? "{$state}日前" : null)
                    ->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'view' => Pages\ViewPlan::route('/{record}'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
