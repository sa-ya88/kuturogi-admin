<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kuturogi_room_id')->nullable()->unique();
            $table->string('name');
            $table->integer('price_per_person')->default(0);
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->json('images')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kuturogi_plan_id')->nullable()->unique();
            $table->string('name');
            $table->integer('price_per_person')->default(0);
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->boolean('has_breakfast')->default(false);
            $table->boolean('has_dinner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->unique(['plan_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_room');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('rooms');
    }
};
