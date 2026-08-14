<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'has_checkout_time')) {
                $table->boolean('has_checkout_time')->default(false);
            }
            if (! Schema::hasColumn('plans', 'checkout_time')) {
                $table->time('checkout_time')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('plans', 'has_checkout_time') ? 'has_checkout_time' : null,
                Schema::hasColumn('plans', 'checkout_time') ? 'checkout_time' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
