<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VisitPriorityController extends Controller
{
    public function update(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('visits.prioritize');
        $visit = $patient->visits()->findOrFail($visit);
        $senior = $patient->isSeniorOn($visit->visit_date->toDateString());
        if ($senior && $request->input('priority') === 'PRIORITY' && ! $request->filled('priority_reason')) {
            $request->merge(['priority_reason' => 'SENIOR']);
        }
        $data = $request->validate(Visit::priorityRules($senior) + [
            'priority_rank' => ['prohibited'], 'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'],
        ]);

        $changed = DB::transaction(function () use ($request, $patient, $visit, $data) {
            $locked = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'OPEN') {
                throw ValidationException::withMessages(['priority' => 'Only open visits can have their priority changed.']);
            }
            $from = ['priority' => $locked->priority, 'priority_reason' => $locked->priority_reason];
            $locked->applyPriority($data);
            if (! $locked->isDirty(['priority', 'priority_reason', 'priority_note'])) {
                return false;
            }
            $locked->save();
            // Reason codes only; the free-text Other note stays out of the audit log.
            activity('visits')->causedBy($request->user())->performedOn($locked)
                ->withProperties(['patient_id' => $patient->id, 'from' => $from, 'to' => ['priority' => $locked->priority, 'priority_reason' => $locked->priority_reason]])
                ->log('visit.priority_changed');
            if ($locked->priority === 'EMERGENCY' && $from['priority'] !== 'EMERGENCY') {
                WorkflowAlerts::emergency($locked, $request->user());
            }

            return true;
        });

        return back()->with('status', $changed ? 'Visit priority updated to '.$visit->fresh()->priorityLabel().'.' : 'Visit priority unchanged.');
    }
}
