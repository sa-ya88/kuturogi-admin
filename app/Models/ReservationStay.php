<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationStay extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_unit_id',
        'representative_name',
        'checked_in_at',
        'checked_out_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function roomUnit(): BelongsTo
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null && $this->checked_out_at === null;
    }

    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    public function canCheckIn(): bool
    {
        return $this->room_unit_id !== null
            && $this->checked_in_at === null
            && filled($this->representative_name);
    }

    public function canCheckOut(): bool
    {
        return $this->isCheckedIn();
    }
}
