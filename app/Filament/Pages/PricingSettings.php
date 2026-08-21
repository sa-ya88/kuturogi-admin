<?php

namespace App\Filament\Pages;

use App\Models\PricingSeasonRate;
use App\Services\PricingSettingsService;
use App\Support\FieldLimits;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PricingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-yen';

    protected static ?string $navigationGroup = '設定';

    protected static ?string $navigationLabel = '料金';

    protected static ?string $title = '料金設定';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.pricing-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(PricingSettingsService $service): void
    {
        $this->form->fill($service->formState());
    }

    public function form(Form $form): Form
    {
        $disabled = ! (auth()->user()?->isAdmin() ?? false);

        return $form
            ->statePath('data')
            ->disabled($disabled)
            ->schema([
                Forms\Components\Section::make('週末・祝前日料金')
                    ->description('通常料金に対する割増（％）。例: 20 = 2割増')
                    ->schema([
                        Forms\Components\TextInput::make('weekend.friday_percent')
                            ->label('金曜日')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(FieldLimits::PERCENT)
                            ->suffix('%増')
                            ->required(),
                        Forms\Components\TextInput::make('weekend.saturday_percent')
                            ->label('土曜日')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(FieldLimits::PERCENT)
                            ->suffix('%増')
                            ->required(),
                        Forms\Components\TextInput::make('weekend.sunday_percent')
                            ->label('日曜日')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(FieldLimits::PERCENT)
                            ->suffix('%増')
                            ->required(),
                        Forms\Components\TextInput::make('weekend.holiday_percent')
                            ->label('祝日')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(FieldLimits::PERCENT)
                            ->suffix('%増')
                            ->required()
                            ->helperText('祝日判定の自動化は後続対応。ここでは割増率のみ設定します'),
                        Forms\Components\TextInput::make('weekend.day_before_holiday_percent')
                            ->label('祝前日')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(FieldLimits::PERCENT)
                            ->suffix('%増')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('季節・イベント料金（シーズン料金）')
                    ->description('シーズン料金は土日祝日料金より優先。複数重なる場合は優先順位が大きいものが適用されます。割増・割引の両方を設定できます')
                    ->schema([
                        Forms\Components\Repeater::make('season_rates')
                            ->label('シーズン料金')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(FieldLimits::TITLE),
                                Forms\Components\Select::make('kind')
                                    ->label('種別')
                                    ->options(PricingSeasonRate::kindOptions())
                                    ->required()
                                    ->native(false)
                                    ->default(PricingSeasonRate::KIND_CUSTOM),
                                Forms\Components\TextInput::make('priority')
                                    ->label('優先順位')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(FieldLimits::SORT)
                                    ->default(0)
                                    ->required()
                                    ->helperText('数値が大きいほど優先'),
                                Forms\Components\Select::make('adjustment_type')
                                    ->label('調整')
                                    ->options(PricingSeasonRate::adjustmentTypeOptions())
                                    ->required()
                                    ->native(false)
                                    ->default(PricingSeasonRate::ADJUSTMENT_SURCHARGE),
                                Forms\Components\DatePicker::make('date_from')
                                    ->label('開始日')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('Y/m/d'),
                                Forms\Components\DatePicker::make('date_to')
                                    ->label('終了日')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('Y/m/d')
                                    ->afterOrEqual('date_from'),
                                Forms\Components\TextInput::make('percent')
                                    ->label('割合')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(FieldLimits::PERCENT)
                                    ->suffix('%')
                                    ->required()
                                    ->helperText('割増または割引の％'),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('有効')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel('シーズン料金を追加')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('子供料金')
                    ->description('大人料金に対する割合（％）。予約の子ども人数と連動する単一設定です')
                    ->schema([
                        Forms\Components\Hidden::make('child_rate.id'),
                        Forms\Components\TextInput::make('child_rate.name')
                            ->label('区分名')
                            ->required()
                            ->maxLength(FieldLimits::TITLE)
                            ->default('子供')
                            ->helperText('例: 子供・小学生以下'),
                        Forms\Components\TextInput::make('child_rate.percent_of_adult')
                            ->label('大人比')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->helperText('例: 70 = 大人の70%'),
                        Forms\Components\Toggle::make('child_rate.is_active')
                            ->label('有効')
                            ->default(true),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('オプション料金')
                    ->description('駐車場代・アーリーチェックインなど、個別オプションの定額料金')
                    ->schema([
                        Forms\Components\Repeater::make('option_fees')
                            ->label('オプション')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('name')
                                    ->label('名称')
                                    ->required()
                                    ->maxLength(FieldLimits::TITLE),
                                Forms\Components\TextInput::make('price')
                                    ->label('料金')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(FieldLimits::PRICE)
                                    ->prefix('¥')
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('有効')
                                    ->default(true),
                                Forms\Components\Textarea::make('description')
                                    ->label('説明')
                                    ->rows(2)
                                    ->maxLength(FieldLimits::DESCRIPTION)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel('オプションを追加')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('キャンセル料・無断不泊課金')
                    ->description('表示名と「何日前から〜何日前まで」、課金％を複数行で設定します。無断不泊も1行として登録してください（課金は手動対応）')
                    ->schema([
                        Forms\Components\Repeater::make('cancel_rules')
                            ->label('キャンセル・無断不泊')
                            ->schema([
                                Forms\Components\Hidden::make('id'),
                                Forms\Components\TextInput::make('label')
                                    ->label('表示名')
                                    ->required()
                                    ->maxLength(FieldLimits::TITLE)
                                    ->helperText('例: 3日前〜前日、当日（連絡あり）、無断不泊'),
                                Forms\Components\TextInput::make('days_before_from')
                                    ->label('何日前から')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(FieldLimits::DAYS)
                                    ->required()
                                    ->helperText('大きい日数側（例: 3）。無断不泊は 0'),
                                Forms\Components\TextInput::make('days_before_to')
                                    ->label('何日前まで')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(FieldLimits::DAYS)
                                    ->required()
                                    ->helperText('小さい日数側（例: 1=前日、0=当日）'),
                                Forms\Components\TextInput::make('charge_percent')
                                    ->label('課金')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required(),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('有効')
                                    ->default(true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('ルールを追加')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(PricingSettingsService $service): void
    {
        if (! auth()->user()?->isAdmin()) {
            Notification::make()
                ->title('保存できません')
                ->body('料金設定の変更は管理者のみ可能です。')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();
        $service->save($data);
        $this->form->fill($service->formState());

        Notification::make()
            ->title('料金設定を保存しました')
            ->success()
            ->send();
    }

    public function canSave(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
