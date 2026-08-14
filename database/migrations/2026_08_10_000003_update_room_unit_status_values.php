<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_units')->where('status', 'available')->update(['status' => 'bookable']);
        DB::table('room_units')->where('status', 'maintenance')->update(['status' => 'unavailable']);
    }

    public function down(): void
    {
        DB::table('room_units')->where('status', 'bookable')->update(['status' => 'available']);
        DB::table('room_units')
            ->whereIn('status', ['unavailable', 'awaiting_arrival', 'in_house', 'needs_cleaning'])
            ->update(['status' => 'maintenance']);
    }
};
