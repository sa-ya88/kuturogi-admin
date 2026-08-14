<?php

namespace App\Models;

use App\Services\RoomAvailabilitySnapshot;
use App\Services\RoomInventoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Room extends Model
{
    protected $fillable = [
        'kuturogi_room_id',
        'name',
        'price_per_person',
        'stock_count',
        'available_from',
        'available_to',
        'description',
        'features',
        'images',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'images' => 'array',
            'is_active' => 'boolean',
            'available_from' => 'date',
            'available_to' => 'date',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomInventory::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(RoomUnit::class)->orderBy('sort_order')->orderBy('code');
    }

    public function inServiceUnitsCount(): int
    {
        return $this->units()
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->count();
    }

    /**
     * 稼働中の個別客室数を stock_count に反映し、日別在庫を再同期する。
     */
    public function syncStockCountFromUnits(bool $syncInventories = true): void
    {
        $previous = null;

        if ($this->available_from) {
            try {
                $previous = RoomAvailabilitySnapshot::fromRoom($this);
            } catch (\Throwable) {
                $previous = null;
            }
        }

        $newStock = $this->inServiceUnitsCount();

        if ((int) $this->stock_count === $newStock) {
            return;
        }

        $this->forceFill(['stock_count' => $newStock])->saveQuietly();

        if (! $syncInventories || ! $this->available_from) {
            return;
        }

        try {
            app(RoomInventoryService::class)->syncInventoriesForRoom($this->fresh(), $previous);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync inventories after stock_count update from units.', [
                'room_id' => $this->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
