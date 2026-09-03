<?php

namespace App\Support;

class PlanChoiceOptions
{
    public static function normalize(?array $options): ?array
    {
        if (empty($options)) {
            return null;
        }

        $normalized = collect($options)
            ->map(function (array $item): ?array {
                $prompt = trim((string) ($item['prompt'] ?? ''));

                $choices = collect($item['choices'] ?? [])
                    ->map(fn ($choice): ?string => filled($choice['label'] ?? null) ? trim((string) $choice['label']) : null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->map(fn (string $label): array => ['label' => $label])
                    ->all();

                if ($prompt === '' || $choices === []) {
                    return null;
                }

                return [
                    'prompt' => $prompt,
                    'choices' => $choices,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $normalized === [] ? null : $normalized;
    }
}
