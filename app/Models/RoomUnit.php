<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

class RoomUnit extends Model
{
    public const OPERATION_IN_SERVICE = 'in_service';

    public const OPERATION_OUT_OF_SERVICE = 'out_of_service';

    public const CURRENT_BOOKABLE = 'bookable';

    public const CURRENT_AWAITING_ARRIVAL = 'awaiting_arrival';

    public const CURRENT_IN_HOUSE = 'in_house';

    public const CURRENT_NEEDS_CLEANING = 'needs_cleaning';

    public const CURRENT_UNAVAILABLE = 'unavailable';

    /** 日付別ステータス: その日に予約（占有）あり */
    public const DAY_RESERVED = 'reserved';

    /** @deprecated Use CURRENT_BOOKABLE */
    public const STATUS_BOOKABLE = self::CURRENT_BOOKABLE;

    /** @deprecated Use CURRENT_AWAITING_ARRIVAL */
    public const STATUS_AWAITING_ARRIVAL = self::CURRENT_AWAITING_ARRIVAL;

    /** @deprecated Use CURRENT_IN_HOUSE */
    public const STATUS_IN_HOUSE = self::CURRENT_IN_HOUSE;

    /** @deprecated Use CURRENT_NEEDS_CLEANING */
    public const STATUS_NEEDS_CLEANING = self::CURRENT_NEEDS_CLEANING;

    /** @deprecated Use CURRENT_UNAVAILABLE */
    public const STATUS_UNAVAILABLE = self::CURRENT_UNAVAILABLE;

    protected $fillable = [
        'room_id',
        'code',
        'notes',
        'operation_status',
        'current_status',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saved(function (RoomUnit $unit): void {
            if ($unit->wasRecentlyCreated
                || $unit->wasChanged(['operation_status', 'room_id'])) {
                static::syncParentStock($unit);
            }
        });

        static::deleted(function (RoomUnit $unit): void {
            static::syncParentStock($unit);
        });
    }

    protected static function syncParentStock(RoomUnit $unit): void
    {
        $roomIds = collect([$unit->room_id, $unit->getOriginal('room_id')])
            ->filter()
            ->unique()
            ->values();

        foreach ($roomIds as $roomId) {
            $room = Room::query()->find($roomId);
            $room?->syncStockCountFromUnits();
        }
    }

    /**
     * @return array<string, string>
     */
    public static function operationStatusOptions(): array
    {
        return [
            self::OPERATION_IN_SERVICE => '稼働中',
            self::OPERATION_OUT_OF_SERVICE => '停止中',
        ];
    }

    /**
     * 編集画面用（ハウスキーピングの現在値）。
     *
     * @return array<string, string>
     */
    public static function currentStatusOptions(): array
    {
        return [
            self::CURRENT_BOOKABLE => '予約可',
            self::CURRENT_AWAITING_ARRIVAL => '到着待ち',
            self::CURRENT_IN_HOUSE => '滞在中',
            self::CURRENT_NEEDS_CLEANING => '要清掃',
            self::CURRENT_UNAVAILABLE => '予約不可',
        ];
    }

    /**
     * 一覧の日付別ステータス表示用（予約有を含む）。
     *
     * @return array<string, string>
     */
    public static function dayStatusOptions(): array
    {
        return [
            self::CURRENT_BOOKABLE => '予約可',
            self::DAY_RESERVED => '予約有',
            self::CURRENT_AWAITING_ARRIVAL => '到着待ち',
            self::CURRENT_IN_HOUSE => '滞在中',
            self::CURRENT_NEEDS_CLEANING => '要清掃',
            self::CURRENT_UNAVAILABLE => '予約不可',
        ];
    }

    /**
     * @deprecated Use currentStatusOptions()
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return self::currentStatusOptions();
    }

    /**
     * 指定日のステータス（一覧表示用）。
     */
    public function dayStatusForDate(Carbon|string $date): string
    {
        $dateString = Carbon::parse($date)->toDateString();
        $isToday = $dateString === now()->toDateString();

        if ($this->operation_status === self::OPERATION_OUT_OF_SERVICE) {
            return self::CURRENT_UNAVAILABLE;
        }

        if ($this->current_status === self::CURRENT_UNAVAILABLE) {
            return self::CURRENT_UNAVAILABLE;
        }

        $hasOccupancy = $this->hasOccupancyOnDate($dateString);

        if ($hasOccupancy) {
            if ($isToday) {
                if ($this->current_status === self::CURRENT_IN_HOUSE) {
                    return self::CURRENT_IN_HOUSE;
                }

                if ($this->current_status === self::CURRENT_AWAITING_ARRIVAL) {
                    return self::CURRENT_AWAITING_ARRIVAL;
                }
            }

            return self::DAY_RESERVED;
        }

        if ($isToday && $this->current_status === self::CURRENT_NEEDS_CLEANING) {
            return self::CURRENT_NEEDS_CLEANING;
        }

        return self::CURRENT_BOOKABLE;
    }

    public function hasOccupancyOnDate(Carbon|string $date): bool
    {
        $dateString = Carbon::parse($date)->toDateString();

        if ($this->relationLoaded('dateOccupancies')) {
            return $this->dateOccupancies->contains(
                fn (RoomUnitDateOccupancy $occupancy): bool => $occupancy->date->toDateString() === $dateString
            );
        }

        return $this->dateOccupancies()->whereDate('date', $dateString)->exists();
    }

    public function dayStatusLabel(Carbon|string $date): string
    {
        $status = $this->dayStatusForDate($date);

        return self::dayStatusOptions()[$status] ?? $status;
    }

    public function dayStatusColor(Carbon|string $date): string
    {
        return match ($this->dayStatusForDate($date)) {
            self::CURRENT_BOOKABLE => 'success',
            self::DAY_RESERVED => 'primary',
            self::CURRENT_AWAITING_ARRIVAL => 'info',
            self::CURRENT_IN_HOUSE => 'warning',
            self::CURRENT_NEEDS_CLEANING => 'gray',
            self::CURRENT_UNAVAILABLE => 'danger',
            default => 'gray',
        };
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withTimestamps();
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ReservationStay::class);
    }

    public function dateOccupancies(): HasMany
    {
        return $this->hasMany(RoomUnitDateOccupancy::class);
    }

    public function scopeInService(Builder $query): Builder
    {
        return $query->where('operation_status', self::OPERATION_IN_SERVICE);
    }

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->inService();
    }

    public function displayLabel(): string
    {
        $roomName = $this->relationLoaded('room')
            ? $this->room?->name
            : $this->room()->value('name');

        return filled($roomName)
            ? "{$this->code}（{$roomName}）"
            : $this->code;
    }

    /**
     * 客室タイプに紐づくプラン + 個別客室に紐づくプランの和集合。
     *
     * @return Collection<int, Plan>
     */
    public function effectivePlans(): Collection
    {
        $this->loadMissing(['room.plans', 'plans']);

        $typePlans = $this->room?->plans ?? new Collection;

        return $typePlans
            ->concat($this->plans)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @return SupportCollection<int, string>
     */
    public function effectivePlanNames(): SupportCollection
    {
        return $this->effectivePlans()->pluck('name')->filter()->values();
    }

    public function operationStatusLabel(): string
    {
        return self::operationStatusOptions()[$this->operation_status] ?? (string) $this->operation_status;
    }

    public function currentStatusLabel(): string
    {
        return self::currentStatusOptions()[$this->current_status] ?? (string) $this->current_status;
    }

    public function operationStatusColor(): string
    {
        return match ($this->operation_status) {
            self::OPERATION_IN_SERVICE => 'success',
            self::OPERATION_OUT_OF_SERVICE => 'danger',
            default => 'gray',
        };
    }

    public function currentStatusColor(): string
    {
        return match ($this->current_status) {
            self::CURRENT_BOOKABLE => 'success',
            self::CURRENT_AWAITING_ARRIVAL => 'info',
            self::CURRENT_IN_HOUSE => 'warning',
            self::CURRENT_NEEDS_CLEANING => 'gray',
            self::CURRENT_UNAVAILABLE => 'danger',
            default => 'gray',
        };
    }

    /** @deprecated Use currentStatusLabel() */
    public function statusLabel(): string
    {
        return $this->currentStatusLabel();
    }

    /** @deprecated Use currentStatusColor() */
    public function statusColor(): string
    {
        return $this->currentStatusColor();
    }
}
