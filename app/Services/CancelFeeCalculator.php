<?php

namespace App\Services;

use App\Models\PricingCancelRule;
use App\Models\Reservation;
use Illuminate\Support\Carbon;

class CancelFeeCalculator
{
    public function calculate(Reservation $reservation, ?Carbon $asOf = null): int
    {
        $asOf ??= now()->startOfDay();
        $checkin = Carbon::parse($reservation->checkin_date)->startOfDay();
        $daysBefore = (int) $asOf->diffInDays($checkin, false);

        if ($daysBefore < 0) {
            $daysBefore = 0;
        }

        $rule = PricingCancelRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(function (PricingCancelRule $rule) use ($daysBefore): bool {
                $from = (int) $rule->days_before_from;
                $to = (int) $rule->days_before_to;

                return $daysBefore <= $from && $daysBefore >= $to;
            });

        if (! $rule) {
            return 0;
        }

        $base = (int) $reservation->total_price;

        return (int) round($base * ((int) $rule->charge_percent) / 100);
    }
}
