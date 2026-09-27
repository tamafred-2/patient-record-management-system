<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('midwife_care_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('care_type', 40);
            $table->text('purpose');
            $table->text('services_provided')->nullable();
            $table->text('notes')->nullable();
            $table->string('location', 200)->nullable();
            $table->date('follow_up_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_token')->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('midwife_care_records');
    }
};
