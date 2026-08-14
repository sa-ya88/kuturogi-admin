<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->string('stripe_payment_intent_id')->nullable()->after('payment_status');
            $table->string('stripe_latest_charge_id')->nullable()->after('stripe_payment_intent_id');
            $table->timestamp('authorized_at')->nullable()->after('stripe_latest_charge_id');
            $table->timestamp('paid_at')->nullable()->after('authorized_at');
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->unsignedInteger('cancel_fee_amount')->nullable()->after('refunded_at');
            $table->string('stripe_cancel_fee_payment_intent_id')->nullable()->after('cancel_fee_amount');
            $table->boolean('cancel_fee_uncollected')->default(false)->after('stripe_cancel_fee_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'stripe_payment_intent_id',
                'stripe_latest_charge_id',
                'authorized_at',
                'paid_at',
                'refunded_at',
                'cancel_fee_amount',
                'stripe_cancel_fee_payment_intent_id',
                'cancel_fee_uncollected',
            ]);
        });
    }
};
