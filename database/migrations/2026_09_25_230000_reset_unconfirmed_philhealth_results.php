<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // An old unchecked checkbox did not establish that no record was found.
        DB::table('eligibility_checks')->where('philhealth_confirmed', false)->update([
            'philhealth_confirmed' => null,
            'lock_version' => DB::raw('lock_version + 1'),
        ]);
    }

    public function down(): void
    {
        // Do not invent a result for records that require a fresh check.
    }
};
