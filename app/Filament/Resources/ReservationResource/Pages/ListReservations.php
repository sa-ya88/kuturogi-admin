<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Filament\Resources\ReservationResource\Concerns\RemembersReservationListFilters;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;

class ListReservations extends ListRecords
{
    use RemembersReservationListFilters;

    protected static string $resource = ReservationResource::class;

    #[Locked]
    public ?string $filterDate = null;

    #[Locked]
    public ?string $filterMonth = null;

    #[Locked]
    public ?string $filterFrom = null;

    #[Locked]
    public ?string $filterTo = null;

    #[Locked]
    public ?int $filterRoomId = null;

    #[Locked]
    public ?int $filterPlanId = null;

    public function mount(): void
    {
        $filters = static::extractListFiltersFromQuery(request()->query());

        if ($filters === []) {
            $this->redirect(static::reservationListUrl(static::recalledListFilters()), navigate: true);

            return;
        }

        parent::mount();

        $this->applyFilters($filters);
        static::rememberListFilters($filters);
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                EditAction::make()
                    ->label('編集')
                    ->url(function (Reservation $record): string {
                        $query = $this->currentFilterQuery();
                        $url = ReservationResource::getUrl('edit', ['record' => $record]);

                        return $query === [] ? $url : $url.'?'.http_build_query($query);
                    }),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if ($this->filterRoomId) {
            $query->where('room_id', $this->filterRoomId);
        }

        if ($this->filterPlanId) {
            $query->where('plan_id', $this->filterPlanId);
        }

        if ($this->filterDate) {
            $date = Carbon::parse($this->filterDate)->startOfDay();

            $query
                ->whereDate('checkin_date', '<=', $date)
                ->whereDate('checkout_date', '>', $date);
        }

        if ($this->filterMonth) {
            $monthStart = Carbon::parse($this->filterMonth.'-01')->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $query
                ->whereDate('checkin_date', '<=', $monthEnd)
                ->whereDate('checkout_date', '>', $monthStart);
        }

        if ($this->filterFrom && $this->filterTo) {
            $from = Carbon::parse($this->filterFrom)->startOfDay();
            $to = Carbon::parse($this->filterTo)->startOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            $query
                ->whereDate('checkin_date', '<=', $to)
                ->whereDate('checkout_date', '>', $from);
        }

        if ($this->hasActiveFilters()) {
            $query->where('status', '!=', Reservation::STATUS_CANCELLED);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calendar')
                ->label('カレンダー表示')
                ->icon('heroicon-o-calendar-days')
                ->url(ReservationResource::getUrl('index')),
            Actions\CreateAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        $parts = [];

        if ($this->filterRoomId) {
            $roomName = Room::query()->find($this->filterRoomId)?->name;
            if ($roomName) {
                $parts[] = "客室: {$roomName}";
            }
        }

        if ($this->filterPlanId) {
            $planName = Plan::query()->find($this->filterPlanId)?->name;
            if ($planName) {
                $parts[] = "プラン: {$planName}";
            }
        }

        if ($this->filterDate) {
            $parts[] = '日付: '.Carbon::parse($this->filterDate)->format('Y年n月j日');
        }

        if ($this->filterMonth) {
            $parts[] = '月: '.Carbon::parse($this->filterMonth.'-01')->format('Y年n月');
        }

        if ($this->filterFrom && $this->filterTo) {
            $from = Carbon::parse($this->filterFrom)->format('Y年n月j日');
            $to = Carbon::parse($this->filterTo)->format('Y年n月j日');
            $parts[] = "期間: {$from} 〜 {$to}";
        }

        if ($parts === []) {
            return null;
        }

        return implode(' / ', $parts).' の予約';
    }

    protected function hasActiveFilters(): bool
    {
        return filled($this->filterDate)
            || filled($this->filterMonth)
            || filled($this->filterFrom)
            || filled($this->filterTo)
            || filled($this->filterRoomId)
            || filled($this->filterPlanId);
    }

    protected function applyFilters(array $filters): void
    {
        $this->filterDate = isset($filters['date']) ? (string) $filters['date'] : null;
        $this->filterMonth = isset($filters['month']) ? (string) $filters['month'] : null;
        $this->filterFrom = isset($filters['from']) ? (string) $filters['from'] : null;
        $this->filterTo = isset($filters['to']) ? (string) $filters['to'] : null;
        $this->filterRoomId = isset($filters['room_id']) ? (int) $filters['room_id'] : null;
        $this->filterPlanId = isset($filters['plan_id']) ? (int) $filters['plan_id'] : null;
    }

    protected function currentFilterQuery(): array
    {
        return array_filter([
            'from' => $this->filterFrom,
            'to' => $this->filterTo,
            'date' => $this->filterDate,
            'month' => $this->filterMonth,
            'room_id' => $this->filterRoomId,
            'plan_id' => $this->filterPlanId,
        ], fn ($value) => filled($value));
    }
}
