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
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        $rooms = DB::table('rooms')->orderBy('id')->pluck('id');

        foreach ($rooms as $index => $roomId) {
            DB::table('rooms')->where('id', $roomId)->update(['sort_order' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
