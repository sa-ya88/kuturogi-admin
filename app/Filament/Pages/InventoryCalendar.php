<?php

namespace App\Filament\Pages;

use App\Models\Room;
use App\Models\RoomInventory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class InventoryCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    public const MAX_DAILY_RANGE_DAYS = 30;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = '在庫カレンダー';

    protected static ?string $title = '在庫カレンダー';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.inventory-calendar';

    /** @var array{periodMode: string, from: ?string, to: ?string} */
    public array $filters = [
        'periodMode' => 'day',
        'from' => null,
        'to' => null,
    ];

    #[Locked]
    public string $appliedPeriodMode = 'day';

    #[Locked]
    public ?string $appliedFrom = null;

    #[Locked]
    public ?string $appliedTo = null;

    public function mount(): void
    {
        $this->appliedFrom = now()->format('Y-m-d');
        $this->appliedTo = now()->addDays(13)->format('Y-m-d');
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
                DatePicker::make('from')
                    ->label('開始')
                    ->helperText('日付表示時は最大30日間。月表示の場合は月初の日付を選んでください')
                    ->required()
                    ->native(false)
                    ->displayFormat('Y/m/d'),
                DatePicker::make('to')
                    ->label('終了')
                    ->helperText('日付表示時は開始日から30日以内')
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
            ->columns(3);
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
        $periodMode = $this->appliedPeriodMode;
        $from = $this->appliedFrom ?? now()->format('Y-m-d');
        $to = $this->appliedTo ?? now()->addDays(13)->format('Y-m-d');

        if ($periodMode === 'month') {
            $columns = $this->monthlyColumns($from, $to);
            $rangeStart = Carbon::parse($from.'-01')->startOfMonth()->format('Y-m-d');
            $rangeEnd = Carbon::parse($to.'-01')->endOfMonth()->format('Y-m-d');
        } else {
            $columns = $this->dailyColumns($from, $to);
            $rangeStart = Carbon::parse($from)->format('Y-m-d');
            $rangeEnd = Carbon::parse($to)->format('Y-m-d');
        }

        $rooms = Room::query()->where('is_active', true)->orderBy('name')->get();

        $inventories = RoomInventory::query()
            ->whereBetween('date', [$rangeStart, $rangeEnd])
            ->get()
            ->groupBy(fn (RoomInventory $item) => $item->room_id.'_'.$item->date->format('Y-m-d'));

        $rows = [];

        foreach ($rooms as $room) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column] = $this->cellValue($inventories, $room->id, $periodMode, $column);
            }

            $rows[] = [
                'room' => $room->name,
                'cells' => $cells,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'column_header' => $periodMode === 'month' ? '月' : '日付',
        ];
    }

    public function formatColumnHeader(string $column): string
    {
        if ($this->appliedPeriodMode === 'month') {
            return Carbon::parse($column.'-01')->format('Y年n月');
        }

        return Carbon::parse($column)->format('n/j');
    }

    /**
     * @return array<int, string>
     */
    protected function dailyColumns(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }

    /**
     * @return array<int, string>
     */
    protected function monthlyColumns(string $fromMonth, string $toMonth): array
    {
        $start = Carbon::parse($fromMonth.'-01')->startOfMonth();
        $end = Carbon::parse($toMonth.'-01')->startOfMonth();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $months = [];

        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * @param  Collection<string, Collection<int, RoomInventory>>  $inventories
     */
    protected function cellValue(Collection $inventories, int $roomId, string $periodMode, string $column): int|string
    {
        if ($periodMode === 'month') {
            $monthStart = Carbon::parse($column.'-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $min = null;

            for ($date = $monthStart->copy(); $date->lte($monthEnd); $date->addDay()) {
                $key = $roomId.'_'.$date->format('Y-m-d');
                $remains = $inventories->get($key)?->first()?->remains;

                if ($remains === null) {
                    continue;
                }

                $min = $min === null ? $remains : min($min, $remains);
            }

            return $min ?? '-';
        }

        $key = $roomId.'_'.$column;

        return $inventories->get($key)?->first()?->remains ?? '-';
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
            'from' => $from,
            'to' => $to,
        ];

        $this->form->fill($this->filters);
    }
}
