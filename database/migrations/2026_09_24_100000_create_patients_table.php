<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_number')->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex', 50)->nullable();
            $table->string('civil_status', 50)->nullable();
            $table->string('contact_number', 40)->nullable();
            $table->string('barangay', 150)->nullable();
            $table->string('municipality', 150)->nullable();
            $table->string('province', 150)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 40)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('duplicate_key', 64)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->index(['last_name', 'first_name']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
