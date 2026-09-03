<?php

namespace App\Models;

use App\Services\KuturogiSyncService;
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
        'details',
        'images',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'details' => 'array',
            'images' => 'array',
            'is_active' => 'boolean',
            'available_from' => 'date',
            'available_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Room $room): void {
            if (! $room->kuturogi_room_id || empty(config('kuturogi.api_key')) || app()->runningUnitTests()) {
                return;
            }

            app(KuturogiSyncService::class)->deleteRoomOnKuturogi($room);
        });
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

    public function hasBlockingReservations(): bool
    {
        return $this->reservations()->exists();
    }

    public function deletionBlockedMessage(): string
    {
        $total = $this->reservations()->count();
        $upcoming = $this->reservations()
            ->where('status', '!=', Reservation::STATUS_CANCELLED)
            ->whereDate('checkout_date', '>=', now()->toDateString())
            ->count();
        $cancelled = $this->reservations()
            ->where('status', Reservation::STATUS_CANCELLED)
            ->count();

        $details = ["全{$total}件"];

        if ($upcoming > 0) {
            $details[] = "今後の有効な予約{$upcoming}件";
        }

        if ($cancelled > 0) {
            $details[] = "キャンセル済み{$cancelled}件";
        }

        return 'この客室タイプは予約履歴があるため削除できません（'.implode('、', $details).'）。'
            .'過去の予約も含めデータを残す必要があるため、削除ではなく「公開」をOFFにするとサイトのお部屋一覧から非表示になります。';
    }

    public function units(): HasMany
    {
        return $this->hasMany(RoomUnit::class)->orderByRoomNumber();
    }

    public function inServiceUnitsCount(): int
    {
        return $this->units()
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->count();
    }

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
