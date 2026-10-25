<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records what was actually given when it differs from the prescription, and closes a remaining
// quantity the patient will buy outside or declined. Existing rows stay plain releases.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensing_items', function (Blueprint $table) {
            $table->string('released_as', 150)->nullable()->after('quantity_dispensed');
            $table->string('remainder_outcome', 20)->nullable()->after('released_as');
            $table->decimal('quantity_closed', 10, 2)->default(0)->after('remainder_outcome');
            $table->string('outcome_note', 500)->nullable()->after('quantity_closed');
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_items', function (Blueprint $table) {
            $table->dropColumn(['released_as', 'remainder_outcome', 'quantity_closed', 'outcome_note']);
        });
    }
};
