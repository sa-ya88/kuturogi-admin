<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRecord extends Model
{
    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reservation_id',
        'amount',
        'recorded_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
