<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            $table->string('operation_status')->default('in_service')->after('notes');
            $table->string('current_status')->default('bookable')->after('operation_status');
        });

        $units = DB::table('room_units')->select('id', 'status', 'is_active')->get();

        foreach ($units as $unit) {
            $oldStatus = (string) ($unit->status ?? 'bookable');
            $isActive = (bool) ($unit->is_active ?? true);

            $operationStatus = (! $isActive || $oldStatus === 'unavailable')
                ? 'out_of_service'
                : 'in_service';

            $currentStatus = match ($oldStatus) {
                'available' => 'bookable',
                'maintenance' => 'unavailable',
                'bookable', 'awaiting_arrival', 'in_house', 'needs_cleaning', 'unavailable' => $oldStatus,
                default => 'bookable',
            };

            if ($operationStatus === 'out_of_service' && $currentStatus === 'bookable') {
                $currentStatus = 'unavailable';
            }

            DB::table('room_units')->where('id', $unit->id)->update([
                'operation_status' => $operationStatus,
                'current_status' => $currentStatus,
            ]);
        }

        Schema::table('room_units', function (Blueprint $table) {
            $table->dropColumn(['status', 'is_active']);
        });

        Schema::create('room_unit_date_occupancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_unit_id')->constrained('room_units')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['room_unit_id', 'date']);
            $table->index('reservation_id');
            $table->index('date');
        });

        $assignedStays = DB::table('reservation_stays')
            ->join('reservations', 'reservations.id', '=', 'reservation_stays.reservation_id')
            ->whereNotNull('reservation_stays.room_unit_id')
            ->where('reservations.status', '!=', 'cancelled')
            ->whereNull('reservation_stays.checked_out_at')
            ->select([
                'reservation_stays.room_unit_id',
                'reservation_stays.reservation_id',
                'reservations.checkin_date',
                'reservations.checkout_date',
            ])
            ->get();

        $now = now();

        foreach ($assignedStays as $stay) {
            $from = Carbon::parse($stay->checkin_date)->startOfDay();
            $to = Carbon::parse($stay->checkout_date)->startOfDay();

            for ($date = $from->copy(); $date->lt($to); $date->addDay()) {
                DB::table('room_unit_date_occupancies')->updateOrInsert(
                    [
                        'room_unit_id' => $stay->room_unit_id,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'reservation_id' => $stay->reservation_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_unit_date_occupancies');

        Schema::table('room_units', function (Blueprint $table) {
            $table->string('status')->default('bookable')->after('notes');
            $table->boolean('is_active')->default(true)->after('status');
        });

        $units = DB::table('room_units')->select('id', 'operation_status', 'current_status')->get();

        foreach ($units as $unit) {
            DB::table('room_units')->where('id', $unit->id)->update([
                'status' => $unit->current_status ?: 'bookable',
                'is_active' => ($unit->operation_status ?? 'in_service') === 'in_service',
            ]);
        }

        Schema::table('room_units', function (Blueprint $table) {
            $table->dropColumn(['operation_status', 'current_status']);
        });
    }
};
