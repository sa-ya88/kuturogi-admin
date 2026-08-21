<?php

namespace App\Support;

use App\Models\RoomDetailOption;

class RoomDetails
{
    /**
     * @param  list<string>  $selected
     * @return array<string, string>
     */
    public static function facilityOptions(array $selected = []): array
    {
        return RoomDetailOption::optionsForSelect(RoomDetailOption::CATEGORY_FACILITY, $selected);
    }

    /**
     * @param  list<string>  $selected
     * @return array<string, string>
     */
    public static function amenityOptions(array $selected = []): array
    {
        return RoomDetailOption::optionsForSelect(RoomDetailOption::CATEGORY_AMENITY, $selected);
    }

    /**
     * @return array<string, string>
     */
    public static function smokingOptions(): array
    {
        return self::options([
            '全室禁煙',
            '全室禁煙（喫煙スペースあり）',
            '喫煙可',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @return array{facilities: list<string>, internet: ?string, smoking: ?string, amenities: list<string>}
     */
    public static function normalize(?array $details): array
    {
        $details ??= [];

        return [
            'facilities' => self::stringList($details['facilities'] ?? []),
            'internet' => self::nullableString($details['internet'] ?? null),
            'smoking' => self::nullableString($details['smoking'] ?? null),
            'amenities' => self::stringList($details['amenities'] ?? []),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private static function options(array $names): array
    {
        return array_combine($names, $names) ?: [];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
            fn (string $item): bool => $item !== ''
        ));
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
