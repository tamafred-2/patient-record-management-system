<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('followup_authorizations', function (Blueprint $table) {
            $table->boolean('allow_any_test')->default(false);
            $table->json('allowed_tests')->nullable();
        });
        Schema::table('followup_authorizations', function (Blueprint $table) {
            $table->string('test_name')->nullable()->change();
        });
        // Existing grants named exactly one test; keep that as their allowed list.
        DB::table('followup_authorizations')->whereNotNull('test_name')->orderBy('id')->each(function ($row) {
            DB::table('followup_authorizations')->where('id', $row->id)->update(['allowed_tests' => json_encode([$row->test_name])]);
        });

        // One order (and one authorization) can now create several single-test laboratory records.
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->dropUnique(['followup_authorization_id']);
            $table->index('followup_authorization_id');
            $table->uuid('order_reference')->nullable()->index();
        });
    }

    public function down(): void
    {
        // Check before changing anything: SQLite runs schema changes without a transaction.
        if (DB::table('laboratory_records')->whereNotNull('followup_authorization_id')->groupBy('followup_authorization_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Some nurse authorizations were used for multi-test orders. Review them before rolling back multi-test laboratory orders.');
        }
        if (DB::table('followup_authorizations')->whereNull('test_name')->exists()) {
            throw new RuntimeException('Some nurse authorizations allow any test or several tests. Revoke or review them before rolling back multi-test laboratory orders.');
        }
        Schema::table('laboratory_records', function (Blueprint $table) {
            $table->dropIndex(['order_reference']);
            $table->dropColumn('order_reference');
            $table->dropIndex(['followup_authorization_id']);
            $table->unique('followup_authorization_id');
        });
        Schema::table('followup_authorizations', function (Blueprint $table) {
            $table->dropColumn(['allow_any_test', 'allowed_tests']);
        });
        Schema::table('followup_authorizations', function (Blueprint $table) {
            $table->string('test_name')->nullable(false)->change();
        });
    }
};
