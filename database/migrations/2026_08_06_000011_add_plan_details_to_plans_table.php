<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'has_checkin_time')) {
                $table->boolean('has_checkin_time')->default(false);
            }
            if (! Schema::hasColumn('plans', 'checkin_time')) {
                $table->time('checkin_time')->nullable();
            }
            if (! Schema::hasColumn('plans', 'has_early_bird')) {
                $table->boolean('has_early_bird')->default(false);
            }
            if (! Schema::hasColumn('plans', 'early_bird_discount_type')) {
                $table->string('early_bird_discount_type', 20)->nullable();
            }
            if (! Schema::hasColumn('plans', 'early_bird_discount_value')) {
                $table->unsignedInteger('early_bird_discount_value')->nullable();
            }
            if (! Schema::hasColumn('plans', 'early_bird_days_before')) {
                $table->unsignedInteger('early_bird_days_before')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('plans', 'has_checkin_time') ? 'has_checkin_time' : null,
                Schema::hasColumn('plans', 'checkin_time') ? 'checkin_time' : null,
                Schema::hasColumn('plans', 'has_early_bird') ? 'has_early_bird' : null,
                Schema::hasColumn('plans', 'early_bird_discount_type') ? 'early_bird_discount_type' : null,
                Schema::hasColumn('plans', 'early_bird_discount_value') ? 'early_bird_discount_value' : null,
                Schema::hasColumn('plans', 'early_bird_days_before') ? 'early_bird_days_before' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
