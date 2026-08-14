<?php

namespace App\Support;

class PlanChoiceOptions
{
    /**
     * @param  array<int, array<string, mixed>>|null  $options
     * @return array<int, array{prompt: string, choices: array<int, array{label: string}>}>|null
     */
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
