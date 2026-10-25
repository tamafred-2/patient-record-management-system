<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

class AccountRetirement
{
    public static function hasLinkedRecords(User $user): bool
    {
        // Discover all user foreign keys, including future clinical modules.
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $key) {
                if ($key['foreign_table'] !== 'users') {
                    continue;
                }
                foreach ($key['columns'] as $column) {
                    if (DB::table($table['name'])->where($column, $user->id)->exists()) {
                        return true;
                    }
                }
            }
        }

        return Activity::where('causer_type', $user->getMorphClass())->where('causer_id', $user->id)->exists();
    }

    public static function endSessions(User $user): void
    {
        $user->password_version++;
        $user->remember_token = null;
        $user->save();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
