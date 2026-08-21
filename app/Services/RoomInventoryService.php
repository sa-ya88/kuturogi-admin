<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\RoomUnit;
use App\Models\RoomUnitDateOccupancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class RoomInventoryService
{
    /**
     * 客室の在庫数・予約可能期間を、在庫カレンダーへ反映する。
     */
    public function syncInventoriesForRoom(Room $room, ?RoomAvailabilitySnapshot $previous = null): int
    {
        $current = RoomAvailabilitySnapshot::fromRoom($room);

        if ($previous !== null && $previous->equals($current)) {
            return 0;
        }

        if ($current->availableFrom->gt($current->availableTo)) {
            throw new RuntimeException('終了日は開始日以降の日付を指定してください。');
        }

        /** @var Collection<int, RoomInventory> $inventoriesToSync */
        $inventoriesToSync = collect();

        $this->clearInventoriesOutsideRange($room, $current, $inventoriesToSync);

        for ($date = $current->availableFrom->copy(); $date->lte($current->availableTo); $date->addDay()) {
            $inventoriesToSync->push(
                $this->upsertRemainsForDate($room, $date->toDateString(), $current->stockCount)
            );
        }

        if ($room->kuturogi_room_id && $inventoriesToSync->isNotEmpty()) {
            app(KuturogiSyncService::class)->pushInventoriesToKuturogi($room, $inventoriesToSync);
        }

        return $inventoriesToSync->count();
    }

    /**
     * 予約の占有状況から残室数を再計算し、在庫カレンダーへ反映する。
     *
     * @param  list<Room>  $rooms
     */
    public function refreshRemainsFromOccupancy(array $rooms, Carbon $from, Carbon $to): void
    {
        foreach ($rooms as $room) {
            $stock = $room->inServiceUnitsCount();

            RoomInventory::query()
                ->where('room_id', $room->id)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereDate('date', '<', $from->toDateString())
                        ->orWhereDate('date', '>', $to->toDateString());
                })
                ->delete();

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $this->upsertRemainsForDate($room, $date->toDateString(), $stock);
            }
        }
    }

    public function upsertRemainsForDate(Room $room, string $date, ?int $stock = null): RoomInventory
    {
        return RoomInventory::upsertForRoomDate(
            $room->id,
            $date,
            $this->remainsFromOccupancy($room, $date, $stock)
        );
    }

    public function remainsFromOccupancy(Room $room, string $date, ?int $stock = null): int
    {
        $stock ??= $room->inServiceUnitsCount();

        $occupied = RoomUnitDateOccupancy::query()
            ->whereDate('date', $date)
            ->whereHas(
                'roomUnit',
                fn ($query) => $query
                    ->where('room_id', $room->id)
                    ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            )
            ->count();

        return max(0, $stock - $occupied);
    }

    /**
     * @deprecated Use syncInventoriesForRoom() instead.
     */
    public function applyStockCount(Room $room, ?int $previousStockCount = null): int
    {
        $previous = $previousStockCount === null
            ? null
            : new RoomAvailabilitySnapshot(
                $previousStockCount,
                Carbon::parse($room->available_from ?? now())->startOfDay(),
                Carbon::parse($room->available_to ?? now()->addMonths((int) config('kuturogi.inventory_horizon_months', 12)))->startOfDay(),
            );

        return $this->syncInventoriesForRoom($room, $previous);
    }

    /**
     * @param  Collection<int, RoomInventory>  $inventoriesToSync
     */
    private function clearInventoriesOutsideRange(
        Room $room,
        RoomAvailabilitySnapshot $current,
        Collection $inventoriesToSync,
    ): void {
        $outsideRange = RoomInventory::query()
            ->where('room_id', $room->id)
            ->where(function ($query) use ($current): void {
                $query->whereDate('date', '<', $current->availableFrom)
                    ->orWhereDate('date', '>', $current->availableTo);
            })
            ->get();

        foreach ($outsideRange as $inventory) {
            $inventory->remains = 0;
            $inventoriesToSync->push($inventory);
            $inventory->delete();
        }
    }
}
