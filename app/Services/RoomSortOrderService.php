<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomSortOrderService
{
    public function __construct(
        protected KuturogiSyncService $syncService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function parseIntegerSortOrder(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                'sort_order' => '表示順を入力してください。',
            ]);
        }

        $normalized = is_string($value) ? trim($value) : (string) $value;

        if (! preg_match('/^\d+$/', $normalized)) {
            throw ValidationException::withMessages([
                'sort_order' => '表示順は整数のみ入力できます。',
            ]);
        }

        $position = (int) $normalized;

        if ($position < 1) {
            throw ValidationException::withMessages([
                'sort_order' => '表示順は 1 以上の整数で入力してください。',
            ]);
        }

        return $position;
    }

    /**
     * 指定客室を targetPosition へ割り込ませ、他の客室を後ろへずらす。
     */
    public function applySortOrder(Room $room, int $targetPosition): void
    {
        DB::transaction(function () use ($room, $targetPosition): void {
            $rooms = Room::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $ordered = $this->insertAtPosition(
                $rooms->reject(fn (Room $candidate): bool => $candidate->is($room))->values(),
                $room,
                $targetPosition,
            );

            $this->renumberRooms($ordered);
        });
    }

    public function syncAllToKuturogi(): void
    {
        Room::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(fn (Room $room) => $this->syncService->pushRoomToKuturogi($room));
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @return Collection<int, Room>
     */
    private function insertAtPosition(Collection $rooms, Room $room, int $targetPosition): Collection
    {
        $index = min(max($targetPosition - 1, 0), $rooms->count());

        return $rooms->take($index)
            ->concat([$room])
            ->concat($rooms->skip($index))
            ->values();
    }

    /**
     * @param  Collection<int, Room>  $rooms
     */
    private function renumberRooms(Collection $rooms): void
    {
        foreach ($rooms as $index => $room) {
            $sortOrder = $index + 1;

            if ((int) $room->sort_order === $sortOrder) {
                continue;
            }

            $room->update(['sort_order' => $sortOrder]);
        }
    }
}
