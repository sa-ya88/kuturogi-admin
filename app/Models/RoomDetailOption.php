<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoomDetailOption extends Model
{
    public const CATEGORY_FACILITY = 'facility';

    public const CATEGORY_AMENITY = 'amenity';

    protected $fillable = [
        'category',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_FACILITY => '客室設備',
            self::CATEGORY_AMENITY => 'アメニティ',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public static function optionsForSelect(string $category, array $selected = []): array
    {
        $options = static::query()
            ->category($category)
            ->active()
            ->ordered()
            ->pluck('name', 'name')
            ->all();

        foreach ($selected as $name) {
            if (is_string($name) && $name !== '' && ! array_key_exists($name, $options)) {
                $options[$name] = "{$name}（未登録）";
            }
        }

        return $options;
    }

    public function isUsedByRooms(): bool
    {
        $key = $this->category === self::CATEGORY_AMENITY ? 'amenities' : 'facilities';

        return Room::query()
            ->whereNotNull('details')
            ->get()
            ->contains(fn (Room $room): bool => in_array($this->name, $room->details[$key] ?? [], true));
    }
}
