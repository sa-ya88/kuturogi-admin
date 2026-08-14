<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::create('plan_room_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'room_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_room_unit');

        Schema::table('room_units', function (Blueprint $table) {
            $table->string('name')->nullable()->after('code');
        });
    }
};
