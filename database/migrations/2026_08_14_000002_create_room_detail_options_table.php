<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_detail_options', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'name']);
        });

        $defaults = [
            'facility' => [
                'バス',
                'シャワー',
                'トイレ',
                '洗浄機能付きトイレ',
                '冷暖房',
                'テレビ',
                '冷蔵庫',
                '金庫',
                '加湿空気清浄機',
            ],
            'amenity' => [
                'タオル',
                '歯ブラシ',
                '浴衣',
                'ドライヤー',
                '石鹸類',
                'シャンプー・リンス',
                'ボディソープ',
                'カミソリ',
                'コットン・綿棒',
            ],
        ];

        $now = now();

        foreach ($defaults as $category => $names) {
            foreach ($names as $index => $name) {
                DB::table('room_detail_options')->insert([
                    'category' => $category,
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('rooms')) {
            return;
        }

        $existing = [
            'facility' => [],
            'amenity' => [],
        ];

        foreach (DB::table('rooms')->whereNotNull('details')->pluck('details') as $detailsJson) {
            $details = is_string($detailsJson) ? json_decode($detailsJson, true) : $detailsJson;

            foreach ($details['facilities'] ?? [] as $name) {
                if (is_string($name) && $name !== '') {
                    $existing['facility'][$name] = true;
                }
            }

            foreach ($details['amenities'] ?? [] as $name) {
                if (is_string($name) && $name !== '') {
                    $existing['amenity'][$name] = true;
                }
            }
        }

        foreach ($existing as $category => $names) {
            $sortOrder = count($defaults[$category]);

            foreach (array_keys($names) as $name) {
                if (in_array($name, $defaults[$category], true)) {
                    continue;
                }

                $sortOrder++;

                DB::table('room_detail_options')->insertOrIgnore([
                    'category' => $category,
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_detail_options');
    }
};
