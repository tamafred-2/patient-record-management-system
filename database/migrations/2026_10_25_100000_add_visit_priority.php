<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('priority', 20)->default('REGULAR');
            $table->unsignedTinyInteger('priority_rank')->default(0);
            $table->string('priority_reason', 20)->nullable();
            $table->string('priority_note', 150)->nullable();
            $table->index(['visit_date', 'priority_rank']);
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['visit_date', 'priority_rank']);
            $table->dropColumn(['priority', 'priority_rank', 'priority_reason', 'priority_note']);
        });
    }
};
