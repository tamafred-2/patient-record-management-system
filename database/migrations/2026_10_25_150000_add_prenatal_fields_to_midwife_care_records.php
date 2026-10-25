<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Basic prenatal check-up items, used only for Prenatal follow-up entries. EDD and age of gestation are
    // calculated from LMP when displayed, not stored.
    public function up(): void
    {
        Schema::table('midwife_care_records', function (Blueprint $table) {
            $table->date('lmp')->nullable();
            $table->unsignedTinyInteger('gravida')->nullable();
            $table->unsignedTinyInteger('para')->nullable();
            $table->decimal('fundal_height_cm', 4, 1)->nullable();
            $table->unsignedSmallInteger('fetal_heart_tone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('midwife_care_records', function (Blueprint $table) {
            $table->dropColumn(['lmp', 'gravida', 'para', 'fundal_height_cm', 'fetal_heart_tone']);
        });
    }
};
