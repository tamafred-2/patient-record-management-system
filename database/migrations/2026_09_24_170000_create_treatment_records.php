<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
        });
        Schema::create('treatment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            foreach (['asthma', 'chest_pain', 'seizure', 'fainting', 'palpitations', 'fever', 'cough_colds', 'allergy', 'family_hypertension', 'taking_medicine'] as $field) {
                $table->boolean($field)->nullable();
            }
            $table->text('medicine_details')->nullable();
            $table->date('lmp')->nullable();
            $table->text('assessment')->nullable();
            $table->text('planning')->nullable();
            $table->text('remarks')->nullable();
            $table->json('objective_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_records');
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropColumn(['respiratory_rate', 'oxygen_saturation']);
        });
    }
};
