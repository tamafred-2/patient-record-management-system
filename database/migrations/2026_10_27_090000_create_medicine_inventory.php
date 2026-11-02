<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pharmacy inventory: medicines, stock received in batches, and a movement ledger. Releases link to the
// medicine actually given, so stock is deducted when medicine is released and restored when a release is voided.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('strength', 50)->nullable();
            $table->string('form', 30);
            // Lower-cased name + strength + form, so the same medicine cannot be listed twice.
            $table->string('lookup_key', 250)->unique();
            $table->decimal('reorder_level', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_on');
            $table->decimal('quantity_received', 10, 2);
            $table->decimal('quantity_remaining', 10, 2);
            $table->string('supplier', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['medicine_id', 'expiry_date']);
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            // Signed: positive adds stock, negative removes it.
            $table->decimal('quantity', 10, 2);
            $table->foreignId('dispensing_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['medicine_id', 'created_at']);
        });
        Schema::table('dispensing_items', function (Blueprint $table) {
            $table->foreignId('medicine_id')->nullable()->after('prescription_item_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medicine_id');
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicines');
    }
};
