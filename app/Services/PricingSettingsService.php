<?php

namespace App\Services;

use App\Models\PricingCancelRule;
use App\Models\PricingChildRate;
use App\Models\PricingOptionFee;
use App\Models\PricingSeasonRate;
use App\Models\PricingWeekendRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        $weekend = PricingWeekendRule::current();

        return [
            'weekend' => [
                'friday_percent' => $weekend->friday_percent,
                'saturday_percent' => $weekend->saturday_percent,
                'sunday_percent' => $weekend->sunday_percent,
                'holiday_percent' => $weekend->holiday_percent,
                'day_before_holiday_percent' => $weekend->day_before_holiday_percent,
            ],
            'season_rates' => PricingSeasonRate::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PricingSeasonRate $rate): array => [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'kind' => $rate->kind,
                    'priority' => $rate->priority,
                    'adjustment_type' => $rate->adjustment_type,
                    'date_from' => $rate->date_from?->toDateString(),
                    'date_to' => $rate->date_to?->toDateString(),
                    'percent' => $rate->percent,
                    'is_active' => $rate->is_active,
                ])
                ->all(),
            'child_rate' => (function (): array {
                $rate = PricingChildRate::current();

                return [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'percent_of_adult' => $rate->percent_of_adult,
                    'is_active' => $rate->is_active,
                ];
            })(),
            'option_fees' => PricingOptionFee::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PricingOptionFee $fee): array => [
                    'id' => $fee->id,
                    'name' => $fee->name,
                    'price' => $fee->price,
                    'description' => $fee->description,
                    'is_active' => $fee->is_active,
                ])
                ->all(),
            'cancel_rules' => PricingCancelRule::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PricingCancelRule $rule): array => [
                    'id' => $rule->id,
                    'label' => $rule->label,
                    'days_before_from' => $rule->days_before_from,
                    'days_before_to' => $rule->days_before_to,
                    'charge_percent' => $rule->charge_percent,
                    'is_active' => $rule->is_active,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $weekend = PricingWeekendRule::current();
            $weekend->update([
                'friday_percent' => (int) ($data['weekend']['friday_percent'] ?? 0),
                'saturday_percent' => (int) ($data['weekend']['saturday_percent'] ?? 0),
                'sunday_percent' => (int) ($data['weekend']['sunday_percent'] ?? 0),
                'holiday_percent' => (int) ($data['weekend']['holiday_percent'] ?? 0),
                'day_before_holiday_percent' => (int) ($data['weekend']['day_before_holiday_percent'] ?? 0),
            ]);

            $this->syncSeasonRates($data['season_rates'] ?? []);
            $this->syncChildRate($data['child_rate'] ?? []);
            $this->syncOptionFees($data['option_fees'] ?? []);
            $this->syncCancelRules($data['cancel_rules'] ?? []);
        });

        $this->pushToKuturogi();
    }

    public function pushToKuturogi(): void
    {
        try {
            $response = app(KuturogiApiClient::class)->pushPricingSettings($this->formState());
            if ($response->failed()) {
                Log::warning('Failed to push pricing settings to kuturogi.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to push pricing settings to kuturogi.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncSeasonRates(array $rows): void
    {
        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $payload = [
                'name' => (string) ($row['name'] ?? ''),
                'kind' => (string) ($row['kind'] ?? PricingSeasonRate::KIND_CUSTOM),
                'priority' => (int) ($row['priority'] ?? 0),
                'adjustment_type' => (string) ($row['adjustment_type'] ?? PricingSeasonRate::ADJUSTMENT_SURCHARGE),
                'date_from' => $row['date_from'] ?? null,
                'date_to' => $row['date_to'] ?? null,
                'percent' => (int) ($row['percent'] ?? 0),
                'sort_order' => $index + 1,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $id = $row['id'] ?? null;
            if ($id) {
                $rate = PricingSeasonRate::query()->find($id);
                if ($rate) {
                    $rate->update($payload);
                    $keptIds[] = $rate->id;

                    continue;
                }
            }

            $keptIds[] = PricingSeasonRate::query()->create($payload)->id;
        }

        if ($keptIds !== []) {
            PricingSeasonRate::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            PricingSeasonRate::query()->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function syncChildRate(array $row): void
    {
        $rate = PricingChildRate::current();

        $rate->update([
            'name' => (string) ($row['name'] ?? '子供'),
            'percent_of_adult' => (int) ($row['percent_of_adult'] ?? 70),
            'sort_order' => 1,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ]);

        PricingChildRate::query()
            ->where('id', '!=', $rate->id)
            ->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncOptionFees(array $rows): void
    {
        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $payload = [
                'name' => (string) ($row['name'] ?? ''),
                'price' => (int) ($row['price'] ?? 0),
                'description' => $row['description'] ?? null,
                'sort_order' => $index + 1,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $id = $row['id'] ?? null;
            if ($id && ($fee = PricingOptionFee::query()->find($id))) {
                $fee->update($payload);
                $keptIds[] = $fee->id;

                continue;
            }

            $keptIds[] = PricingOptionFee::query()->create($payload)->id;
        }

        if ($keptIds !== []) {
            PricingOptionFee::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            PricingOptionFee::query()->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function syncCancelRules(array $rows): void
    {
        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $from = (int) ($row['days_before_from'] ?? 0);
            $to = (int) ($row['days_before_to'] ?? 0);

            $payload = [
                'label' => (string) ($row['label'] ?? ''),
                'days_before_from' => max($from, $to),
                'days_before_to' => min($from, $to),
                'is_no_show' => false,
                'charge_percent' => (int) ($row['charge_percent'] ?? 0),
                'sort_order' => $index + 1,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $id = $row['id'] ?? null;
            if ($id && ($rule = PricingCancelRule::query()->find($id))) {
                $rule->update($payload);
                $keptIds[] = $rule->id;

                continue;
            }

            $keptIds[] = PricingCancelRule::query()->create($payload)->id;
        }

        if ($keptIds !== []) {
            PricingCancelRule::query()->whereNotIn('id', $keptIds)->delete();
        } else {
            PricingCancelRule::query()->delete();
        }
    }
}
