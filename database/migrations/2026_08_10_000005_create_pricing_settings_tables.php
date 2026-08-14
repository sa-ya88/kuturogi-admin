<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_weekend_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('friday_percent')->default(0);
            $table->unsignedInteger('saturday_percent')->default(0);
            $table->unsignedInteger('sunday_percent')->default(0);
            $table->unsignedInteger('holiday_percent')->default(0);
            $table->unsignedInteger('day_before_holiday_percent')->default(0);
            $table->timestamps();
        });

        Schema::create('pricing_season_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('custom');
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('surcharge_percent')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_child_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('percent_of_adult')->default(100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_option_fees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_cancel_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedInteger('days_before_from')->default(0);
            $table->unsignedInteger('days_before_to')->default(0);
            $table->boolean('is_no_show')->default(false);
            $table->unsignedInteger('charge_percent')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('pricing_weekend_rules')->insert([
            'friday_percent' => 0,
            'saturday_percent' => 0,
            'sunday_percent' => 0,
            'holiday_percent' => 0,
            'day_before_holiday_percent' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('pricing_child_rates')->insert([
            'name' => '子供',
            'percent_of_adult' => 70,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('pricing_cancel_rules')->insert([
            [
                'label' => '3日前〜前日',
                'days_before_from' => 3,
                'days_before_to' => 1,
                'is_no_show' => false,
                'charge_percent' => 20,
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => '当日（連絡あり）',
                'days_before_from' => 0,
                'days_before_to' => 0,
                'is_no_show' => false,
                'charge_percent' => 80,
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'label' => '無断不泊',
                'days_before_from' => 0,
                'days_before_to' => 0,
                'is_no_show' => true,
                'charge_percent' => 100,
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_cancel_rules');
        Schema::dropIfExists('pricing_option_fees');
        Schema::dropIfExists('pricing_child_rates');
        Schema::dropIfExists('pricing_season_rates');
        Schema::dropIfExists('pricing_weekend_rules');
    }
};
