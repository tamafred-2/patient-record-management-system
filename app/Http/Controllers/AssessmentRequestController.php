<?php

namespace App\Http\Controllers;

use App\Models\AssessmentRequest;
use App\Models\Patient;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentRequestController extends Controller
{
    public function store(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        Gate::authorize('assessments.request');
        $visit = $patient->visits()->findOrFail($visit);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'assessment_version' => ['required', 'integer', 'min:1'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'], 'requested_by' => ['prohibited'], 'status' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if (! $visit->isOpen() || ! $visit->vitalSign) {
                throw ValidationException::withMessages(['reassessment' => 'Reassessment requires an open visit with recorded vital signs.']);
            }
            if ($visit->pendingAssessmentRequest) {
                throw ValidationException::withMessages(['reassessment' => 'A reassessment request is already pending.']);
            }
            if ((int) $data['assessment_version'] !== $visit->vitalSign->lock_version) {
                throw ValidationException::withMessages(['reassessment' => 'The assessment changed. Reload and review the latest assessment before requesting another.']);
            }
            $entry = new AssessmentRequest;
            $entry->visit_id = $visit->id;
            $entry->requested_by = $request->user()->id;
            $entry->reason = $data['reason'];
            $entry->assessment_version = $data['assessment_version'];
            $entry->status = 'PENDING';
            $entry->save();
            activity('assessments')->causedBy($request->user())->performedOn($entry)
                ->withProperties(['visit_id' => $visit->id])->log('reassessment.requested');
            WorkflowAlerts::send('reassessment_requested', $visit, WorkflowAlerts::nurses(), $request->user());
        });

        return redirect()->route('consultations.show', [$patient, $visit])->with('status', 'Reassessment requested. Nurses can see it in Initial assessment.');
    }

    public function cancel(Request $request, Patient $patient, string $visit, string $assessmentRequest)
    {
        Gate::authorize('consultations.view');
        Gate::authorize('assessments.request');
        $visit = $patient->visits()->findOrFail($visit);
        $data = $request->validate(['resolution_note' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $patient, $visit, $assessmentRequest, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $entry = $visit->assessmentRequests()->findOrFail($assessmentRequest);
            if (! $visit->isOpen() || $entry->status !== 'PENDING') {
                throw ValidationException::withMessages(['reassessment' => 'Only pending requests on open visits can be withdrawn.']);
            }
            $entry->status = 'CANCELLED';
            $entry->resolution_note = $data['resolution_note'];
            $entry->resolved_by = $request->user()->id;
            $entry->resolved_at = now();
            $entry->save();
            activity('assessments')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $visit->id])->log('reassessment.cancelled');
        });

        return redirect()->route('consultations.show', [$patient, $visit])->with('status', 'Reassessment request withdrawn.');
    }
}
