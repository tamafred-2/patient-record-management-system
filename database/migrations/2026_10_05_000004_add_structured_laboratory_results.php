<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->json('result_rows')->nullable();
            $table->string('specimen', 200)->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->dropColumn(['result_rows', 'specimen', 'completed_at']);
        });
    }
};
