<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type')->default('guest')->after('kuturogi_user_id');
            $table->json('tags')->nullable()->after('gender');
            $table->timestamp('last_stayed_at')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['type', 'tags', 'last_stayed_at']);
        });
    }
};
