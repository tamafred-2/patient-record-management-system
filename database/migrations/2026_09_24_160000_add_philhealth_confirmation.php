<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eligibility_checks', function (Blueprint $table) {
            $table->boolean('philhealth_confirmed')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eligibility_checks', function (Blueprint $table) {
            $table->dropColumn('philhealth_confirmed');
        });
    }
};
