<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingWeekendRule extends Model
{
    protected $fillable = [
        'friday_percent',
        'saturday_percent',
        'sunday_percent',
        'holiday_percent',
        'day_before_holiday_percent',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'friday_percent' => 0,
            'saturday_percent' => 0,
            'sunday_percent' => 0,
            'holiday_percent' => 0,
            'day_before_holiday_percent' => 0,
        ]);
    }
}
