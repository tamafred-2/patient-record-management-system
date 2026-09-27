<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->nullable()->unique();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('treatment_record_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('DRAFT');
            $table->timestamp('prescribed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->string('medicine_name', 200);
            $table->string('strength', 100)->nullable();
            $table->string('dosage', 200);
            $table->string('frequency', 200);
            $table->string('duration', 200)->nullable();
            $table->text('instructions')->nullable();
            $table->decimal('quantity_prescribed', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
