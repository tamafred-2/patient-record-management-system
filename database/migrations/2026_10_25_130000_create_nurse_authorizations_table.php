<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nurse_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->boolean('allow_any_test')->default(false);
            $table->json('allowed_tests')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['nurse_id', 'revoked_at']);
        });
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->foreignId('nurse_authorization_id')->nullable()->constrained('nurse_authorizations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Dropping the link would relabel nurse orders as doctor orders; check before changing anything.
        if (DB::table('laboratory_records')->whereNotNull('nurse_authorization_id')->exists()) {
            throw new RuntimeException('Laboratory orders were placed under standing nurse authorization. Review them before rolling back nurse authorizations.');
        }
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nurse_authorization_id');
        });
        Schema::dropIfExists('nurse_authorizations');
    }
};
