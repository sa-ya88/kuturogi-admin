<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationCalendarService
{
    /**
     * @return array<int, string>
     */
    public function dailyColumns(string $from, string $to): array
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
     * @return array<int, string> YYYY-MM
     */
    public function monthlyColumns(string $fromMonth, string $toMonth): array
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
     * @return Collection<int, Room|Plan>
     */
    public function rows(string $rowMode): Collection
    {
        return match ($rowMode) {
            'plan' => Plan::query()->where('is_active', true)->orderBy('name')->get(),
            default => Room::query()->where('is_active', true)->orderBy('name')->get(),
        };
    }

    /**
     * @return array{dates: array<int, string>, rows: array<int, array{label: string, row_id: int, cells: array<string, array{count: int}>}>}
     */
    public function buildGrid(
        string $periodMode,
        string $rowMode,
        string $from,
        string $to,
    ): array {
        if ($periodMode === 'month') {
            $from = Carbon::parse($from)->startOfMonth()->format('Y-m');
            $to = Carbon::parse($to)->startOfMonth()->format('Y-m');
        } else {
            $from = Carbon::parse($from)->format('Y-m-d');
            $to = Carbon::parse($to)->format('Y-m-d');
        }

        $columns = $periodMode === 'month'
            ? $this->monthlyColumns($from, $to)
            : $this->dailyColumns($from, $to);

        $rangeStart = $periodMode === 'month'
            ? Carbon::parse($from.'-01')->startOfMonth()
            : Carbon::parse($from)->startOfDay();

        $rangeEnd = $periodMode === 'month'
            ? Carbon::parse($to.'-01')->endOfMonth()
            : Carbon::parse($to)->startOfDay();

        $reservations = Reservation::query()
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->whereDate('checkin_date', '<=', $rangeEnd)
            ->whereDate('checkout_date', '>', $rangeStart)
            ->get(['room_id', 'plan_id', 'checkin_date', 'checkout_date']);

        $rowKey = $rowMode === 'plan' ? 'plan_id' : 'room_id';
        $rows = [];

        foreach ($this->rows($rowMode) as $row) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column] = [
                    'count' => $this->countForCell($reservations, $row->id, $rowKey, $periodMode, $column),
                ];
            }

            $rows[] = [
                'label' => $row->name,
                'row_id' => $row->id,
                'cells' => $cells,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'column_header' => $periodMode === 'month' ? '月' : '日付',
            'row_header' => $rowMode === 'plan' ? 'プラン' : '客室',
        ];
    }

    protected function countForCell(
        Collection $reservations,
        int $rowId,
        string $rowKey,
        string $periodMode,
        string $column,
    ): int {
        return $reservations
            ->filter(function (Reservation $reservation) use ($rowId, $rowKey, $periodMode, $column): bool {
                if ($reservation->{$rowKey} !== $rowId) {
                    return false;
                }

                if ($periodMode === 'month') {
                    $monthStart = Carbon::parse($column.'-01')->startOfMonth();
                    $monthEnd = $monthStart->copy()->endOfMonth();

                    return $reservation->checkin_date->lte($monthEnd)
                        && $reservation->checkout_date->gt($monthStart);
                }

                $date = Carbon::parse($column)->startOfDay();

                return $reservation->checkin_date->lte($date)
                    && $reservation->checkout_date->gt($date);
            })
            ->count();
    }
}
