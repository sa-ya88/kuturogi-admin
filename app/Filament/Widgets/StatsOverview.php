<?php

namespace App\Filament\Widgets;

use App\Models\Reservation;
use App\Models\SalesRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        return [
            Stat::make('本日チェックイン', Reservation::where('status', Reservation::STATUS_CONFIRMED)
                ->whereDate('checkin_date', $today)->count())
                ->description('本日到着予定')
                ->icon('heroicon-o-arrow-right-end-on-rectangle'),

            Stat::make('本日チェックアウト', Reservation::where('status', Reservation::STATUS_CONFIRMED)
                ->whereDate('checkout_date', $today)->count())
                ->description('本日退室予定')
                ->icon('heroicon-o-arrow-right-start-on-rectangle'),

            Stat::make('確定予約', Reservation::where('status', Reservation::STATUS_CONFIRMED)->count())
                ->description('キャンセル除く')
                ->icon('heroicon-o-calendar-days'),

            Stat::make('今月売上', '¥'.number_format(
                SalesRecord::where('status', SalesRecord::STATUS_RECORDED)
                    ->where('recorded_at', '>=', $monthStart)
                    ->sum('amount')
            ))
                ->description(now()->format('Y年n月'))
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
