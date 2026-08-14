<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('会員数', Customer::members()->count())
                ->description('kuturogi 登録会員')
                ->icon('heroicon-o-user-circle'),

            Stat::make('ゲスト数', Customer::guests()->count())
                ->description('予約のみの顧客')
                ->icon('heroicon-o-user'),

            Stat::make('リピーター', Customer::repeaters()->count())
                ->description('2回以上宿泊')
                ->icon('heroicon-o-arrow-path'),

            Stat::make('VIP', Customer::vip()->count())
                ->description('累計10万円以上')
                ->icon('heroicon-o-star'),
        ];
    }
}
