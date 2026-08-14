<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Support\Carbon;

readonly class RoomAvailabilitySnapshot
{
    public function __construct(
        public int $stockCount,
        public Carbon $availableFrom,
        public Carbon $availableTo,
    ) {}

    public static function fromRoom(Room $room): self
    {
        $horizonMonths = (int) config('kuturogi.inventory_horizon_months', 12);

        return new self(
            (int) $room->stock_count,
            Carbon::parse($room->available_from ?? now())->startOfDay(),
            Carbon::parse(
                $room->available_to ?? now()->addMonths($horizonMonths)
            )->startOfDay(),
        );
    }

    public function equals(self $other): bool
    {
        return $this->stockCount === $other->stockCount
            && $this->availableFrom->toDateString() === $other->availableFrom->toDateString()
            && $this->availableTo->toDateString() === $other->availableTo->toDateString();
    }
}
