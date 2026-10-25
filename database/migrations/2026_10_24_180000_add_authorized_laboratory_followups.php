<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('nurse_id')->constrained('users')->restrictOnDelete();
            $table->string('test_name');
            $table->text('instructions');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['visit_id', 'nurse_id']);
        });
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->foreignId('followup_authorization_id')->nullable()->unique()->constrained('followup_authorizations')->restrictOnDelete();
            $table->foreignId('authorizing_doctor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('request_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('followup_authorization_id');
            $table->dropConstrainedForeignId('authorizing_doctor_id');
            $table->dropColumn('request_reason');
        });
        Schema::dropIfExists('followup_authorizations');
    }
};
