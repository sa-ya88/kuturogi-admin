<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_feature_options', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaults = [
            '和室',
            '和洋室',
            '禁煙',
            '露天風呂付',
            '定員1〜4名',
            '定員2〜4名',
            '定員2〜3名',
        ];

        foreach ($defaults as $index => $name) {
            DB::table('room_feature_options')->insert([
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('rooms')) {
            $existing = [];

            foreach (DB::table('rooms')->whereNotNull('features')->pluck('features') as $featuresJson) {
                foreach (json_decode($featuresJson, true) ?? [] as $feature) {
                    if (is_string($feature) && $feature !== '') {
                        $existing[$feature] = true;
                    }
                }
            }

            $sortOrder = count($defaults);

            foreach (array_keys($existing) as $name) {
                if (in_array($name, $defaults, true)) {
                    continue;
                }

                $sortOrder++;

                DB::table('room_feature_options')->insertOrIgnore([
                    'name' => $name,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_feature_options');
    }
};
