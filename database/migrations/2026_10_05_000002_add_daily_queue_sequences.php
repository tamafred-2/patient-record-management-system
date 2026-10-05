<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_days', function (Blueprint $table) {
            $table->date('queue_date')->primary();
            $table->char('prefix', 2);
            $table->unsignedInteger('last_number')->default(0);
        });
        Schema::table('queue_reservations', function (Blueprint $table) {
            $table->dropUnique(['queue_reference']);
            $table->date('queue_date')->nullable();
            $table->unique(['queue_date', 'queue_reference']);
        });
    }

    public function down(): void
    {
        if (DB::table('queue_reservations')->whereNotNull('queue_reference')->groupBy('queue_reference')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Daily queue references repeat across dates. Review them before rolling back daily numbering.');
        }
        Schema::table('queue_reservations', function (Blueprint $table) {
            $table->dropUnique(['queue_date', 'queue_reference']);
            $table->dropColumn('queue_date');
            $table->unique('queue_reference');
        });
        Schema::dropIfExists('queue_days');
    }
};
