<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    public const TYPE_MEMBER = 'member';
    public const TYPE_GUEST = 'guest';

    public const TAG_REPEATER = 'リピーター';
    public const TAG_VIP = 'VIP';

    protected $fillable = [
        'kuturogi_user_id',
        'type',
        'name',
        'name_kana',
        'email',
        'tel',
        'zip_code',
        'address',
        'birthday',
        'gender',
        'tags',
        'last_stayed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'tags' => 'array',
            'last_stayed_at' => 'datetime',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function confirmedReservations(): HasMany
    {
        return $this->hasMany(Reservation::class)
            ->where('status', Reservation::STATUS_CONFIRMED);
    }

    public function scopeMembers(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_MEMBER);
    }

    public function scopeGuests(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GUEST);
    }

    public function scopeRepeaters(Builder $query): Builder
    {
        return $query->has('reservations', '>=', 2);
    }

    public function scopeVip(Builder $query, int $threshold = 100000): Builder
    {
        return $query->whereRaw('(
            SELECT COALESCE(SUM(total_price), 0)
            FROM reservations
            WHERE reservations.customer_id = customers.id
            AND reservations.status = ?
        ) >= ?', [Reservation::STATUS_CONFIRMED, $threshold]);
    }

    public function getTotalSpentAttribute(): int
    {
        return (int) $this->reservations()
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->sum('total_price');
    }

    public function getReservationCountAttribute(): int
    {
        return $this->reservations()->count();
    }

    public function refreshStayStats(): void
    {
        $lastCheckout = $this->reservations()
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->max('checkout_date');

        $this->update([
            'last_stayed_at' => $lastCheckout ? \Illuminate\Support\Carbon::parse($lastCheckout) : null,
        ]);

        $this->refreshAutoTags();
    }

    public function refreshAutoTags(): void
    {
        $manualTags = collect($this->tags ?? [])
            ->filter(fn ($tag) => ! in_array($tag, [self::TAG_REPEATER, self::TAG_VIP], true))
            ->values();

        $autoTags = [];

        if ($this->reservation_count >= 2) {
            $autoTags[] = self::TAG_REPEATER;
        }

        if ($this->total_spent >= 100000) {
            $autoTags[] = self::TAG_VIP;
        }

        $this->update([
            'tags' => $manualTags->merge($autoTags)->unique()->values()->all(),
        ]);
    }

    public function isMember(): bool
    {
        return $this->type === self::TYPE_MEMBER;
    }
}
