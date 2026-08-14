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
            $table->unsignedInteger('stock_count')->default(0)->after('price_per_person');
        });

        if (Schema::hasTable('room_inventories')) {
            $rooms = DB::table('rooms')->pluck('id');

            foreach ($rooms as $roomId) {
                $maxRemains = DB::table('room_inventories')
                    ->where('room_id', $roomId)
                    ->where('date', '>=', now()->toDateString())
                    ->max('remains');

                if ($maxRemains !== null) {
                    DB::table('rooms')->where('id', $roomId)->update([
                        'stock_count' => (int) $maxRemains,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('stock_count');
        });
    }
};
