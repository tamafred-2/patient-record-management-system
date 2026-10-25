<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\FollowupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// A doctor schedules (or clears) a follow-up return date for a visit.
class FollowUpScheduleController extends Controller
{
    public function update(Request $request, Patient $patient, string $visit)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        $today = now('Asia/Manila')->toDateString();
        $data = $request->validate([
            'clear' => ['sometimes', 'accepted'],
            'follow_up_on' => ['exclude_with:clear', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before_or_equal:'.now('Asia/Manila')->addYear()->toDateString()],
            'follow_up_reason' => ['exclude_with:clear', 'required', 'string', 'max:500'],
            'follow_up_set_by' => ['prohibited'], 'follow_up_notified_at' => ['prohibited'],
        ]);
        $cleared = DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $clear = isset($data['clear']);
            $visit->follow_up_on = $clear ? null : $data['follow_up_on'];
            $visit->follow_up_reason = $clear ? null : $data['follow_up_reason'];
            $visit->follow_up_set_by = $clear ? null : $request->user()->id;
            $visit->follow_up_set_at = $clear ? null : now();
            // Only a new, changed or cleared date re-arms the reminder; editing the reason alone does not resend it.
            if ($visit->isDirty('follow_up_on')) {
                $visit->follow_up_notified_at = null;
            }
            $visit->save();
            activity('visits')->causedBy($request->user())->performedOn($visit)
                ->withProperties(['patient_id' => $patient->id, 'follow_up_on' => $visit->follow_up_on?->toDateString()])
                ->log($clear ? 'visit.follow_up_cleared' : 'visit.follow_up_scheduled');

            return $clear;
        });

        return back()->with('status', $cleared ? 'Follow-up return visit removed.' : 'Follow-up return visit scheduled. Front desk staff will see it when it is due.');
    }
}
