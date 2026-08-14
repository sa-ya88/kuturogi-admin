<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingOptionFee extends Model
{
    protected $fillable = [
        'name',
        'price',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
