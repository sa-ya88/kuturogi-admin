<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomFeatureOption extends Model
{
    protected $fillable = [
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForSelect(?Room $room = null): array
    {
        $options = static::query()
            ->active()
            ->ordered()
            ->pluck('name', 'name')
            ->all();

        foreach ($room?->features ?? [] as $feature) {
            if (! array_key_exists($feature, $options)) {
                $options[$feature] = "{$feature}（未登録）";
            }
        }

        return $options;
    }

    public function isUsedByRooms(): bool
    {
        return Room::query()
            ->whereNotNull('features')
            ->get()
            ->contains(fn (Room $room): bool => in_array($this->name, $room->features ?? [], true));
    }
}
