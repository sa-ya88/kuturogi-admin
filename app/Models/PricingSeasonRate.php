<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingSeasonRate extends Model
{
    public const KIND_OBON = 'obon';

    public const KIND_NEW_YEAR = 'new_year';

    public const KIND_GOLDEN_WEEK = 'golden_week';

    public const KIND_CUSTOM = 'custom';

    public const ADJUSTMENT_SURCHARGE = 'surcharge';

    public const ADJUSTMENT_DISCOUNT = 'discount';

    protected $fillable = [
        'name',
        'kind',
        'priority',
        'adjustment_type',
        'date_from',
        'date_to',
        'percent',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function kindOptions(): array
    {
        return [
            self::KIND_OBON => 'お盆',
            self::KIND_NEW_YEAR => '年末年始',
            self::KIND_GOLDEN_WEEK => 'ゴールデンウィーク',
            self::KIND_CUSTOM => 'その他（個別指定）',
        ];
    }

    public static function adjustmentTypeOptions(): array
    {
        return [
            self::ADJUSTMENT_SURCHARGE => '割増',
            self::ADJUSTMENT_DISCOUNT => '割引',
        ];
    }

    public function signedPercent(): int
    {
        $percent = (int) $this->percent;

        return $this->adjustment_type === self::ADJUSTMENT_DISCOUNT
            ? -$percent
            : $percent;
    }
}
