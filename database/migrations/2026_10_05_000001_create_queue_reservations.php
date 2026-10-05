<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('queue_reference', 100)->nullable()->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_reservations');
    }
};
