<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_reopenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('reopened_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('previous_completed_at')->nullable();
            $table->foreignId('previous_completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('previous_completion_remarks')->nullable();
            $table->timestamps();
        });
        Schema::table('dispensings', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dispensings', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropColumn(['voided_at', 'voided_by', 'void_reason']);
        });
        Schema::dropIfExists('visit_reopenings');
    }
};
