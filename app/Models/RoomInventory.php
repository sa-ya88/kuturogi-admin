<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RoomInventory extends Model
{
    protected $fillable = [
        'room_id',
        'date',
        'remains',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * room_id + date のユニーク制約を守りながら upsert する。
     * updateOrCreate + date cast は SQLite で重複 INSERT になることがあるため明示的に処理。
     */
    public static function upsertForRoomDate(int $roomId, string $date, int $remains, ?Carbon $syncedAt = null): self
    {
        $normalizedDate = Carbon::parse($date)->toDateString();

        $inventory = static::query()
            ->where('room_id', $roomId)
            ->whereDate('date', $normalizedDate)
            ->first();

        $attributes = [
            'remains' => $remains,
            'synced_at' => $syncedAt ?? now(),
        ];

        if ($inventory) {
            $inventory->update($attributes);

            return $inventory->fresh();
        }

        return static::create(array_merge([
            'room_id' => $roomId,
            'date' => $normalizedDate,
        ], $attributes));
    }
}
