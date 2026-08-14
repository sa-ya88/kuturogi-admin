<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomInventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class RoomInventoryService
{
    /**
     * 客室の在庫数・予約可能期間を、在庫カレンダー・在庫管理へ反映する。
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

        $isInitial = $previous === null;
        $oldStock = $previous?->stockCount ?? $current->stockCount;

        /** @var Collection<int, RoomInventory> $inventoriesToSync */
        $inventoriesToSync = collect();

        $this->clearInventoriesOutsideRange($room, $current, $inventoriesToSync);

        for ($date = $current->availableFrom->copy(); $date->lte($current->availableTo); $date->addDay()) {
            $dateString = $date->toDateString();
            $remains = $this->resolveRemains(
                $room,
                $dateString,
                $current->stockCount,
                $oldStock,
                $isInitial,
                $previous,
            );

            $inventoriesToSync->push(
                RoomInventory::upsertForRoomDate($room->id, $dateString, $remains)
            );
        }

        if ($room->kuturogi_room_id && $inventoriesToSync->isNotEmpty()) {
            app(KuturogiSyncService::class)->pushInventoriesToKuturogi($room, $inventoriesToSync);
        }

        return $inventoriesToSync->count();
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

    private function resolveRemains(
        Room $room,
        string $dateString,
        int $newStock,
        int $oldStock,
        bool $isInitial,
        ?RoomAvailabilitySnapshot $previous,
    ): int {
        if ($isInitial) {
            return $newStock;
        }

        $date = Carbon::parse($dateString)->startOfDay();
        $wasInPreviousRange = $previous !== null
            && $date->betweenIncluded($previous->availableFrom, $previous->availableTo);

        if (! $wasInPreviousRange) {
            return $newStock;
        }

        if ($previous->stockCount !== $newStock) {
            $existing = RoomInventory::query()
                ->where('room_id', $room->id)
                ->whereDate('date', $dateString)
                ->first();

            if ($existing) {
                return max(0, $newStock - ($oldStock - $existing->remains));
            }

            return $newStock;
        }

        $existing = RoomInventory::query()
            ->where('room_id', $room->id)
            ->whereDate('date', $dateString)
            ->first();

        return $existing?->remains ?? $newStock;
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
