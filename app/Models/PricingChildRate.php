<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingChildRate extends Model
{
    protected $fillable = [
        'name',
        'percent_of_adult',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $rate = static::query()->orderBy('id')->first();

        if ($rate) {
            return $rate;
        }

        return static::query()->create([
            'name' => '子供',
            'percent_of_adult' => 70,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }
}
