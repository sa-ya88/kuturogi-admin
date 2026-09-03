<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Services\ReservationCalendarService;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ReservationCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    public const MAX_DAILY_RANGE_DAYS = 31;

    protected static string $resource = ReservationResource::class;

    protected static string $view = 'filament.resources.reservation-resource.pages.reservation-calendar';

    protected static ?string $title = '予約管理';

    public array $filters = [
        'periodMode' => 'day',
        'rowMode' => 'room',
        'from' => null,
        'to' => null,
    ];

    #[Locked]
    public string $appliedPeriodMode = 'day';

    #[Locked]
    public string $appliedRowMode = 'room';

    #[Locked]
    public ?string $appliedFrom = null;

    #[Locked]
    public ?string $appliedTo = null;

    public function mount(): void
    {
        [$from, $to] = $this->defaultDateRange();
        $this->appliedFrom = $from;
        $this->appliedTo = $to;
        $this->syncFiltersFromApplied();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->schema([
                Select::make('periodMode')
                    ->label('横軸')
                    ->options([
                        'day' => '日付',
                        'month' => '月',
                    ])
                    ->required(),
                Select::make('rowMode')
                    ->label('縦軸')
                    ->options([
                        'room' => '客室',
                        'plan' => 'プラン',
                    ])
                    ->required(),
                DatePicker::make('from')
                    ->label('開始')
                    ->helperText('日付表示時は最大'.self::MAX_DAILY_RANGE_DAYS.'日間。月表示の場合は月初の日付を選んでください')
                    ->required()
                    ->native(false)
                    ->displayFormat('Y/m/d'),
                DatePicker::make('to')
                    ->label('終了')
                    ->helperText('日付表示時は開始日から'.(self::MAX_DAILY_RANGE_DAYS - 1).'日後まで')
                    ->required()
                    ->native(false)
                    ->displayFormat('Y/m/d')
                    ->afterOrEqual('from')
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if ($get('periodMode') !== 'day' || blank($get('from')) || blank($value)) {
                                return;
                            }

                            $days = Carbon::parse($get('from'))->startOfDay()
                                ->diffInDays(Carbon::parse($value)->startOfDay()) + 1;

                            if ($days > self::MAX_DAILY_RANGE_DAYS) {
                                $fail('日付指定時の表示期間は'.self::MAX_DAILY_RANGE_DAYS.'日以内にしてください。');
                            }
                        },
                    ]),
            ])
            ->columns(4);
    }

    public function applyFilter(): void
    {
        $state = $this->form->getState();

        if (! $this->isDailyRangeValid($state)) {
            $days = Carbon::parse($state['from'])->startOfDay()
                ->diffInDays(Carbon::parse($state['to'])->startOfDay()) + 1;

            $message = '日付指定時の表示期間は'.self::MAX_DAILY_RANGE_DAYS.'日以内にしてください。（指定: '.$days.'日）';

            Notification::make()
                ->title('表示期間が長すぎます')
                ->body($message)
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'filters.to' => $message,
            ]);
        }

        $this->appliedPeriodMode = $state['periodMode'];
        $this->appliedRowMode = $state['rowMode'];

        if ($this->appliedPeriodMode === 'month') {
            $this->appliedFrom = Carbon::parse($state['from'])->startOfMonth()->format('Y-m');
            $this->appliedTo = Carbon::parse($state['to'])->startOfMonth()->format('Y-m');
        } else {
            $this->appliedFrom = Carbon::parse($state['from'])->format('Y-m-d');
            $this->appliedTo = Carbon::parse($state['to'])->format('Y-m-d');
        }
    }

    public function getCalendarData(): array
    {
        [$defaultFrom, $defaultTo] = $this->defaultDateRange();

        return app(ReservationCalendarService::class)->buildGrid(
            $this->appliedPeriodMode,
            $this->appliedRowMode,
            $this->appliedFrom ?? $defaultFrom,
            $this->appliedTo ?? $defaultTo,
        );
    }

    public function cellListUrl(int $rowId, string $column): string
    {
        $query = array_filter([
            $this->appliedRowMode === 'plan' ? 'plan_id' : 'room_id' => $rowId,
            $this->appliedPeriodMode === 'month' ? 'month' : 'date' => $column,
        ]);

        ListReservations::rememberListFilters($query);

        return ListReservations::reservationListUrl($query);
    }

    public function formatColumnHeader(string $column): string
    {
        if ($this->appliedPeriodMode === 'month') {
            return Carbon::parse($column.'-01')->format('Y年n月');
        }

        return Carbon::parse($column)->format('n/j');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('list')
                ->label('一覧表示')
                ->icon('heroicon-o-list-bullet')
                ->url(fn (): string => $this->listUrl()),
            Actions\CreateAction::make()
                ->label('予約を追加'),
        ];
    }

    protected function listUrl(): string
    {
        [$from, $to] = $this->appliedDateRange();

        ListReservations::rememberListFilters([
            'from' => $from,
            'to' => $to,
        ]);

        return ListReservations::reservationListUrl([
            'from' => $from,
            'to' => $to,
        ]);
    }

    protected function appliedDateRange(): array
    {
        [$defaultFrom, $defaultTo] = $this->defaultDateRange();
        $from = $this->appliedFrom ?? $defaultFrom;
        $to = $this->appliedTo ?? $defaultTo;

        if ($this->appliedPeriodMode === 'month') {
            return [
                Carbon::parse($from.'-01')->startOfMonth()->format('Y-m-d'),
                Carbon::parse($to.'-01')->endOfMonth()->format('Y-m-d'),
            ];
        }

        return [
            Carbon::parse($from)->format('Y-m-d'),
            Carbon::parse($to)->format('Y-m-d'),
        ];
    }

    protected function defaultDateRange(): array
    {
        $range = ListReservations::defaultListDateRange();

        return [$range['from'], $range['to']];
    }

    protected function isDailyRangeValid(array $state): bool
    {
        if (($state['periodMode'] ?? 'day') !== 'day') {
            return true;
        }

        if (blank($state['from'] ?? null) || blank($state['to'] ?? null)) {
            return true;
        }

        $days = Carbon::parse($state['from'])->startOfDay()
            ->diffInDays(Carbon::parse($state['to'])->startOfDay()) + 1;

        return $days <= self::MAX_DAILY_RANGE_DAYS;
    }

    protected function syncFiltersFromApplied(): void
    {
        $from = $this->appliedFrom;
        $to = $this->appliedTo;

        if ($this->appliedPeriodMode === 'month') {
            $from = Carbon::parse($from.'-01')->format('Y-m-d');
            $to = Carbon::parse($to.'-01')->format('Y-m-d');
        }

        $this->filters = [
            'periodMode' => $this->appliedPeriodMode,
            'rowMode' => $this->appliedRowMode,
            'from' => $from,
            'to' => $to,
        ];

        $this->form->fill($this->filters);
    }
}
