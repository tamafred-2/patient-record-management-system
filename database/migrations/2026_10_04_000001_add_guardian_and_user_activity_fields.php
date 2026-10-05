<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('guardian_phone', 40)->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_sign_in_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn(['guardian_name', 'guardian_relationship', 'guardian_phone']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['last_sign_in_at', 'last_active_at']));
    }
};
