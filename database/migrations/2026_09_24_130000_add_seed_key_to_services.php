<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->string('seed_key', 50)->nullable()->unique());
        foreach (['CONSULTATION', 'LABORATORY', 'MEDICAL_CERTIFICATE', 'OTHER'] as $code) {
            DB::table('services')->where('code', $code)->update(['seed_key' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['seed_key']);
            $table->dropColumn('seed_key');
        });
    }
};
