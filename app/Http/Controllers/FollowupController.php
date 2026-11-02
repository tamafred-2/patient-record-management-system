<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryRecord;
use App\Models\NurseAuthorization;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\FollowupAccess;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FollowupController extends Controller
{
    private function ready(Visit $visit): void
    {
        if (! $visit->isOpen() || ! $visit->vitalSign) {
            throw ValidationException::withMessages(['followup' => 'Laboratory orders require an open visit with a saved assessment.']);
        }
    }

    public function show(Request $request, Patient $patient, string $visit)
    {
        $user = $request->user();
        $isDoctor = FollowupAccess::doctor($user);
        abort_unless($isDoctor || FollowupAccess::nurse($user), 403);
        $visit = $patient->visits()->with(['service', 'vitalSign.recorder', 'vitalSign.updater'])->findOrFail($visit);
        // Doctors and nurses both see every order on the visit, so nobody orders a test that is already pending.
        $records = $visit->laboratoryRecords()->where(fn ($ordered) => $ordered->whereNotNull('order_reference')->orWhereNotNull('authorizing_doctor_id'))
            ->with(['authorizingDoctor', 'requester', 'followupAuthorization', 'nurseAuthorization'])->latest('id')->get();

        return view('followups.show', compact('patient', 'visit', 'isDoctor', 'records'));
    }

    // Doctors and nurses order laboratory tests the same way; the order records who placed it.
    public function requestTest(Request $request, Patient $patient, string $visit)
    {
        $user = $request->user();
        $isDoctor = FollowupAccess::doctor($user);
        abort_unless($isDoctor || FollowupAccess::nurse($user), 403);
        $data = $request->validate([
            'test_names' => ['required', 'array', 'min:1'],
            'test_names.*' => ['string', 'distinct', Rule::in(NurseAuthorization::catalog())],
            'test_name' => ['prohibited'], 'order_reference' => ['prohibited'], 'authorization_id' => ['prohibited'], 'followup_authorization_id' => ['prohibited'], 'nurse_authorization_id' => ['prohibited'],
            'request_reason' => ['required', 'string', 'max:2000'],
            'assessment_version' => ['required', 'integer', 'min:1'],
            'submission_token' => ['required', 'uuid'],
            'confirm' => ['accepted'],
            'authorizing_doctor_id' => ['prohibited'], 'created_by' => ['prohibited'], 'visit_id' => ['prohibited'], 'patient_id' => ['prohibited'],
        ]);
        $count = DB::transaction(function () use ($user, $isDoctor, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $this->ready($visit);
            if ($visit->vitalSign->lock_version !== (int) $data['assessment_version']) {
                throw ValidationException::withMessages(['followup' => 'The assessment changed. Reload and review it before requesting a test.']);
            }
            $tests = array_values(array_intersect(NurseAuthorization::catalog(), $data['test_names']));
            if (LaboratoryRecord::where('order_reference', $data['submission_token'])->orWhere('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['followup' => 'This request was already submitted. Review the existing request.']);
            }
            $pending = $visit->laboratoryRecords()->whereIn('test_name', $tests)->where('availability_status', 'PENDING')->pluck('test_name')->all();
            if ($pending) {
                throw ValidationException::withMessages(['followup' => 'Already pending for this visit: '.implode(', ', $pending).'. Remove them from this request.']);
            }
            $ids = [];
            foreach ($tests as $test) {
                $entry = new LaboratoryRecord;
                $entry->visit_id = $visit->id;
                $entry->test_name = $test;
                $entry->availability_status = 'PENDING';
                $entry->created_by = $entry->updated_by = $user->id;
                // A doctor's own order names them as the authorizing doctor; a nurse's order needs no doctor.
                $entry->authorizing_doctor_id = $isDoctor ? $user->id : null;
                $entry->request_reason = $data['request_reason'];
                // The form token identifies the whole order; the first record keeps it so repeats are caught.
                $entry->order_reference = $data['submission_token'];
                $entry->submission_token = $ids ? (string) Str::uuid() : $data['submission_token'];
                $entry->lock_version = 1;
                $entry->save();
                $ids[] = $entry->id;
            }
            activity('followups')->causedBy($user)->performedOn($entry)
                ->withProperties(['visit_id' => $visit->id, 'ordered_by_role' => $isDoctor ? 'doctor' : 'nurse', 'laboratory_record_ids' => $ids])->log('followup.requested');
            WorkflowAlerts::send('lab_ordered', $visit, WorkflowAlerts::medtechs(), $user, ['laboratory_record_ids' => $ids]);

            return count($ids);
        });

        return redirect()->route('followups.show', [$patient, $visit])->with('status', $count === 1 ? 'Laboratory test sent to the laboratory as Pending.' : "{$count} laboratory tests sent to the laboratory as Pending.");
    }

    public function slip(Request $request, Patient $patient, string $visit, string $order)
    {
        $user = $request->user();
        $isDoctor = FollowupAccess::doctor($user);
        abort_unless($isDoctor || FollowupAccess::nurse($user) || $user->can('laboratory.view'), 403);
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        $records = $visit->laboratoryRecords()->where('order_reference', $order)->with(['authorizingDoctor', 'requester', 'followupAuthorization', 'nurseAuthorization'])->orderBy('id')->get();
        abort_if($records->isEmpty(), 404);
        activity('followups')->causedBy($user)->performedOn($records->first())->withProperties(['visit_id' => $visit->id, 'laboratory_record_ids' => $records->pluck('id')->all()])->log('followup.slip_viewed');

        return response()->view('followups.slip', compact('patient', 'visit', 'records'))->header('Cache-Control', 'private, no-store');
    }
}
