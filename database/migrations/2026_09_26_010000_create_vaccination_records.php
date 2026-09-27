<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('vaccine_name', 200);
            $table->string('dose', 100);
            $table->date('administered_on');
            $table->string('batch_number', 100)->nullable();
            $table->string('administered_by', 200);
            $table->date('next_appointment')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->uuid('submission_token')->unique();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_records');
    }
};
