<?php

namespace App\Support;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Follow-up return visits a doctor scheduled. No cron is required: open pages trigger the check at most every
// ten minutes, and `php artisan followups:remind` does the same for installations that run the scheduler.
class FollowUpReminders
{
    // Scheduled follow-ups whose patient has not yet had a newer visit on or after the follow-up date.
    public static function outstanding(): Builder
    {
        return Visit::with(['patient', 'service'])->whereHas('patient')->whereNotNull('follow_up_on')
            ->whereNotExists(fn ($later) => $later->select(DB::raw(1))->from('visits as later')
                ->whereColumn('later.patient_id', 'visits.patient_id')->whereColumn('later.id', '>', 'visits.id')
                ->whereColumn('later.visit_date', '>=', 'visits.follow_up_on'));
    }

    public static function dispatchDue(bool $force = false): int
    {
        if (! $force && ! Cache::add('follow-up-reminders', true, 600)) {
            return 0;
        }
        $sent = 0;
        $today = now('Asia/Manila')->toDateString();
        self::outstanding()->whereNull('follow_up_notified_at')->whereDate('follow_up_on', '<=', $today)->orderBy('id')->limit(200)->get()
            ->each(function (Visit $visit) use (&$sent) {
                // Claim the visit first so two concurrent runs cannot both notify.
                if (! Visit::whereKey($visit->id)->whereNull('follow_up_notified_at')->update(['follow_up_notified_at' => now()])) {
                    return;
                }
                WorkflowAlerts::send('followup_due', $visit, WorkflowAlerts::frontDesk()->push(User::find($visit->follow_up_set_by)), null);
                $sent++;
            });

        return $sent;
    }
}
