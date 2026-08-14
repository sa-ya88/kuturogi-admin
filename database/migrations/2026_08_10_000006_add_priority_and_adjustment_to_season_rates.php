<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_season_rates', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->after('kind');
            $table->string('adjustment_type')->default('surcharge')->after('priority');
            $table->unsignedInteger('percent')->default(0)->after('date_to');
        });

        foreach (DB::table('pricing_season_rates')->orderBy('id')->get() as $row) {
            DB::table('pricing_season_rates')->where('id', $row->id)->update([
                'percent' => (int) ($row->surcharge_percent ?? 0),
                'adjustment_type' => 'surcharge',
                'priority' => 0,
            ]);
        }

        Schema::table('pricing_season_rates', function (Blueprint $table) {
            $table->dropColumn('surcharge_percent');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_season_rates', function (Blueprint $table) {
            $table->unsignedInteger('surcharge_percent')->default(0)->after('date_to');
        });

        foreach (DB::table('pricing_season_rates')->orderBy('id')->get() as $row) {
            DB::table('pricing_season_rates')->where('id', $row->id)->update([
                'surcharge_percent' => (int) ($row->percent ?? 0),
            ]);
        }

        Schema::table('pricing_season_rates', function (Blueprint $table) {
            $table->dropColumn(['priority', 'adjustment_type', 'percent']);
        });
    }
};
