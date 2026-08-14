<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const DISCOUNT_TYPE_PERCENT = 'percent';

    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'kuturogi_plan_id',
        'name',
        'price_per_person',
        'description',
        'choice_options',
        'images',
        'has_breakfast',
        'has_dinner',
        'is_active',
        'has_checkin_time',
        'checkin_time',
        'has_checkout_time',
        'checkout_time',
        'has_early_bird',
        'early_bird_discount_type',
        'early_bird_discount_value',
        'early_bird_days_before',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'choice_options' => 'array',
            'has_breakfast' => 'boolean',
            'has_dinner' => 'boolean',
            'is_active' => 'boolean',
            'has_checkin_time' => 'boolean',
            'has_checkout_time' => 'boolean',
            'has_early_bird' => 'boolean',
            'early_bird_discount_value' => 'integer',
            'early_bird_days_before' => 'integer',
        ];
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class);
    }

    public function roomUnits(): BelongsToMany
    {
        return $this->belongsToMany(RoomUnit::class)->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function earlyBirdDiscountTypeLabel(): ?string
    {
        return match ($this->early_bird_discount_type) {
            self::DISCOUNT_TYPE_PERCENT => '割引率（％）',
            self::DISCOUNT_TYPE_FIXED => '固定額（円）',
            default => null,
        };
    }

    public function formattedEarlyBirdDiscount(): ?string
    {
        if (! $this->has_early_bird || $this->early_bird_discount_value === null) {
            return null;
        }

        return match ($this->early_bird_discount_type) {
            self::DISCOUNT_TYPE_PERCENT => "{$this->early_bird_discount_value}％",
            self::DISCOUNT_TYPE_FIXED => number_format($this->early_bird_discount_value).'円',
            default => (string) $this->early_bird_discount_value,
        };
    }

    public function formattedCheckinTime(): ?string
    {
        if (! $this->has_checkin_time || blank($this->checkin_time)) {
            return null;
        }

        return substr((string) $this->checkin_time, 0, 5);
    }

    public function formattedCheckoutTime(): ?string
    {
        if (! $this->has_checkout_time || blank($this->checkout_time)) {
            return null;
        }

        return substr((string) $this->checkout_time, 0, 5);
    }

    public function effectiveCheckinTime(): string
    {
        return $this->formattedCheckinTime() ?? '15:00';
    }

    public function effectiveCheckoutTime(): string
    {
        return $this->formattedCheckoutTime() ?? '11:00';
    }
}
