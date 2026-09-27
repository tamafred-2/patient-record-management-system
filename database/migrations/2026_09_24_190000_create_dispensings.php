<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('pharmacist_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('dispensed_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('dispensing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispensing_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_dispensed', 10, 2);
            $table->unique(['dispensing_id', 'prescription_item_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensing_items');
        Schema::dropIfExists('dispensings');
    }
};
