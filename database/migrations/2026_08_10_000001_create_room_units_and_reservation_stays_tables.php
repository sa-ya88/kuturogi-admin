<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['room_id', 'code']);
        });

        Schema::create('reservation_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->string('representative_name');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['reservation_id', 'sort_order']);
            $table->index(['room_unit_id', 'checked_in_at', 'checked_out_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('stay_status')->default('reserved')->after('status');
        });

        $reservations = DB::table('reservations')->select('id', 'guest_name', 'room_count', 'status')->get();

        foreach ($reservations as $reservation) {
            $roomCount = max(1, (int) $reservation->room_count);
            $guestName = filled($reservation->guest_name) ? (string) $reservation->guest_name : '未設定';

            for ($i = 1; $i <= $roomCount; $i++) {
                DB::table('reservation_stays')->insert([
                    'reservation_id' => $reservation->id,
                    'room_unit_id' => null,
                    'representative_name' => $i === 1 ? $guestName : '',
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('stay_status');
        });

        Schema::dropIfExists('reservation_stays');
        Schema::dropIfExists('room_units');
    }
};
