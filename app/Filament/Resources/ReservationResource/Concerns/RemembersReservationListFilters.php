<?php

namespace App\Filament\Resources\ReservationResource\Concerns;

use App\Filament\Resources\ReservationResource;

trait RemembersReservationListFilters
{
    public const LIST_FILTER_SESSION_KEY = 'reservation_list_filters';

    public static function defaultListDateRange(): array
    {
        $from = now()->subDay()->startOfDay();

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $from->copy()->addDays(7)->format('Y-m-d'),
        ];
    }

    public static function normalizeListFilters(array $filters): array
    {
        $normalized = [];

        foreach (['from', 'to', 'date', 'month'] as $key) {
            if (filled($filters[$key] ?? null)) {
                $normalized[$key] = (string) $filters[$key];
            }
        }

        foreach (['room_id', 'plan_id'] as $key) {
            if (filled($filters[$key] ?? null)) {
                $normalized[$key] = (int) $filters[$key];
            }
        }

        if (! isset($normalized['from'], $normalized['to'])
            && ! isset($normalized['date'])
            && ! isset($normalized['month'])) {
            $normalized = array_merge(static::defaultListDateRange(), $normalized);
        }

        return $normalized;
    }

    public static function rememberListFilters(array $filters): void
    {
        session([self::LIST_FILTER_SESSION_KEY => static::normalizeListFilters($filters)]);
    }

    public static function recalledListFilters(): array
    {
        $stored = session(self::LIST_FILTER_SESSION_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return static::normalizeListFilters($stored);
    }

    public static function reservationListUrl(?array $filters = null): string
    {
        $query = static::normalizeListFilters($filters ?? static::recalledListFilters());

        return ReservationResource::getUrl('list').'?'.http_build_query($query);
    }

    public static function extractListFiltersFromQuery(array $query): array
    {
        return array_filter([
            'from' => $query['from'] ?? null,
            'to' => $query['to'] ?? null,
            'date' => $query['date'] ?? null,
            'month' => $query['month'] ?? null,
            'room_id' => $query['room_id'] ?? null,
            'plan_id' => $query['plan_id'] ?? null,
        ], fn ($value) => filled($value));
    }
}
