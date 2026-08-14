<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->date('available_from')->nullable()->after('stock_count');
            $table->date('available_to')->nullable()->after('available_from');
        });

        $defaultFrom = now()->toDateString();
        $defaultTo = now()->addMonths(12)->toDateString();

        DB::table('rooms')->update([
            'available_from' => $defaultFrom,
            'available_to' => $defaultTo,
        ]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'available_to']);
        });
    }
};
