<?php

namespace App\Filament\Resources\PlanResource\Pages\Concerns;

use App\Support\PlanChoiceOptions;

trait NormalizesPlanFormData
{
    protected function normalizePlanFormData(array $data): array
    {
        if (empty($data['has_checkin_time'])) {
            $data['has_checkin_time'] = false;
            $data['checkin_time'] = null;
        }

        if (empty($data['has_checkout_time'])) {
            $data['has_checkout_time'] = false;
            $data['checkout_time'] = null;
        }

        if (empty($data['has_early_bird'])) {
            $data['has_early_bird'] = false;
            $data['early_bird_discount_type'] = null;
            $data['early_bird_discount_value'] = null;
            $data['early_bird_days_before'] = null;
        }

        $data['choice_options'] = PlanChoiceOptions::normalize($data['choice_options'] ?? null);

        return $data;
    }
}
