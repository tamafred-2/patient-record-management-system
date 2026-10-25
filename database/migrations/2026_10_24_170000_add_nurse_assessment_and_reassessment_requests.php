<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_signs', fn (Blueprint $table) => $table->text('assessment_summary')->nullable());
        Schema::create('assessment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->unsignedInteger('assessment_version');
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedInteger('completed_version')->nullable();
            $table->timestamps();
            $table->index(['visit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_requests');
        Schema::table('vital_signs', fn (Blueprint $table) => $table->dropColumn('assessment_summary'));
    }
};
