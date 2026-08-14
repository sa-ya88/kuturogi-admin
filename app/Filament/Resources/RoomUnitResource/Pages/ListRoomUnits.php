<?php

namespace App\Filament\Resources\RoomUnitResource\Pages;

use App\Filament\Resources\RoomResource;
use App\Filament\Resources\RoomUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListRoomUnits extends ListRecords
{
    protected static string $resource = RoomUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('roomTypes')
                ->label('客室タイプ設定')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->url(RoomResource::getUrl('index')),
            Actions\CreateAction::make()->label('個別客室を追加'),
        ];
    }

    public function resolveViewDate(): string
    {
        $date = data_get($this->tableFilters, 'view_date.date');

        if (blank($date)) {
            return now()->toDateString();
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    protected function getTableQuery(): Builder
    {
        $date = $this->resolveViewDate();
        $dayStatus = data_get($this->tableFilters, 'day_status.value')
            ?? data_get($this->tableFilters, 'day_status');

        $query = parent::getTableQuery()->with([
            'room.plans',
            'plans',
            'dateOccupancies' => fn ($occupancyQuery) => $occupancyQuery->whereDate('date', $date),
        ]);

        if (filled($dayStatus) && is_string($dayStatus)) {
            $query = RoomUnitResource::constrainByDayStatus($query, $dayStatus, $date);
        }

        return $query;
    }
}
