<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->date('follow_up_on')->nullable()->index();
            $table->string('follow_up_reason', 500)->nullable();
            $table->foreignId('follow_up_set_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('follow_up_set_at')->nullable();
            $table->timestamp('follow_up_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['follow_up_on']);
            $table->dropConstrainedForeignId('follow_up_set_by');
            $table->dropColumn(['follow_up_on', 'follow_up_reason', 'follow_up_set_at', 'follow_up_notified_at']);
        });
    }
};
