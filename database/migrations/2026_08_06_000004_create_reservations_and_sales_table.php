<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kuturogi_reservation_id')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->date('checkout_date');
            $table->integer('guest_count');
            $table->integer('room_count')->default(1);
            $table->integer('adult_count')->default(1);
            $table->integer('child_count')->default(0);
            $table->integer('total_price');
            $table->string('status')->default('confirmed');
            $table->string('payment_method')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_name_kana')->nullable();
            $table->string('guest_tel')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('source')->default('kuturogi');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->timestamp('recorded_at');
            $table->string('status')->default('recorded');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_records');
        Schema::dropIfExists('reservations');
    }
};
