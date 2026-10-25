<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryRecord;
use App\Models\NurseAuthorization;
use App\Models\Patient;
use App\Models\User;
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
    private function assessedBy(Visit $visit, User $nurse): bool
    {
        return in_array($nurse->id, [$visit->vitalSign?->recorded_by, $visit->vitalSign?->updated_by], true);
    }

    private function ready(Visit $visit): void
    {
        if (! $visit->isOpen() || ! $visit->vitalSign) {
            throw ValidationException::withMessages(['followup' => 'Follow-up requests require an open visit with a saved assessment.']);
        }
    }

    public function show(Request $request, Patient $patient, string $visit)
    {
        $user = $request->user();
        $isDoctor = FollowupAccess::doctor($user);
        abort_unless($isDoctor || FollowupAccess::nurse($user), 403);
        $visit = $patient->visits()->with(['service', 'vitalSign.recorder', 'vitalSign.updater'])->findOrFail($visit);
        $authorizations = $isDoctor ? collect() : $visit->followupAuthorizations()->with(['doctor', 'nurse', 'revoker'])
            ->where('nurse_id', $user->id)->whereNull('used_at')->whereNull('revoked_at')->latest('id')->get();
        $records = $visit->laboratoryRecords()->whereNotNull('authorizing_doctor_id')->with(['authorizingDoctor', 'requester', 'followupAuthorization', 'nurseAuthorization'])
            ->when(! $isDoctor, fn ($query) => $query->where('created_by', $user->id))->latest('id')->get();
        $standing = FollowupAccess::nurse($user) ? NurseAuthorization::activeFor($user) : null;
        $canOrderAsNurse = $standing?->usable() && $this->assessedBy($visit, $user);

        return view('followups.show', compact('patient', 'visit', 'isDoctor', 'authorizations', 'records', 'standing', 'canOrderAsNurse'));
    }

    // Withdraws an unused legacy per-visit grant. New authority is managed on the Nurse authorization page.
    public function revoke(Request $request, Patient $patient, string $visit, string $authorization)
    {
        abort_unless(FollowupAccess::doctor($request->user()), 403);
        $data = $request->validate(['revocation_reason' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $patient, $visit, $authorization, $data) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $this->ready($visit);
            $entry = $visit->followupAuthorizations()->findOrFail($authorization);
            if ($entry->used_at || $entry->revoked_at) {
                throw ValidationException::withMessages(['followup' => 'This authority was already used or revoked. Reload the page.']);
            }
            $entry->revoked_at = now();
            $entry->revoked_by = $request->user()->id;
            $entry->revocation_reason = $data['revocation_reason'] ?? null;
            $entry->save();
            activity('followups')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $visit->id])->log('followup.revoked');
            $entry->clearNurseAlert();
        });

        return redirect()->route('followups.show', [$patient, $visit])->with('status', 'Unused visit authorization withdrawn.');
    }

    public function requestTest(Request $request, Patient $patient, string $visit)
    {
        $user = $request->user();
        $isDoctor = FollowupAccess::doctor($user);
        abort_unless($isDoctor || FollowupAccess::nurse($user), 403);
        // Doctors order directly; nurses order under their standing authorization, or a legacy per-visit grant.
        $mode = $request->filled('authorization_id') ? 'visit' : ($isDoctor ? 'direct' : 'standing');
        abort_unless($mode === 'direct' || FollowupAccess::nurse($user), 403);
        $data = $request->validate([
            'authorization_id' => [$mode === 'visit' ? 'required' : 'prohibited', 'integer'],
            'test_names' => ['required', 'array', 'min:1'],
            'test_names.*' => ['string', 'distinct', Rule::in(NurseAuthorization::catalog())],
            'test_name' => ['prohibited'], 'order_reference' => ['prohibited'], 'followup_authorization_id' => ['prohibited'], 'nurse_authorization_id' => ['prohibited'],
            'request_reason' => ['required', 'string', 'max:2000'],
            'assessment_version' => ['required', 'integer', 'min:1'],
            'submission_token' => ['required', 'uuid'],
            'confirm' => ['accepted'],
            'authorizing_doctor_id' => ['prohibited'], 'created_by' => ['prohibited'], 'visit_id' => ['prohibited'], 'patient_id' => ['prohibited'],
        ]);
        $count = DB::transaction(function () use ($user, $patient, $visit, $data, $mode) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $this->ready($visit);
            if ($visit->vitalSign->lock_version !== (int) $data['assessment_version']) {
                throw ValidationException::withMessages(['followup' => 'The assessment changed. Reload and review it before requesting a test.']);
            }
            $grant = null;
            $standing = null;
            if ($mode === 'visit') {
                $grant = $visit->followupAuthorizations()->with('doctor')->where('nurse_id', $user->id)->findOrFail($data['authorization_id']);
                if ($grant->used_at || $grant->revoked_at || ! FollowupAccess::doctor($grant->doctor)) {
                    throw ValidationException::withMessages(['followup' => 'This authority is no longer available. Ask a doctor to review it.']);
                }
            }
            if ($mode === 'standing') {
                $standing = NurseAuthorization::active()->where('nurse_id', $user->id)->with('doctor')->lockForUpdate()->latest('id')->first();
                if (! $standing?->usable()) {
                    throw ValidationException::withMessages(['followup' => 'You do not have active laboratory ordering authority. Ask a doctor to turn it on.']);
                }
            }
            if ($mode !== 'direct' && ! $this->assessedBy($visit, $user)) {
                throw ValidationException::withMessages(['followup' => 'Record your assessment of this patient before ordering laboratory tests.']);
            }
            $authority = $grant ?? $standing;
            $tests = array_values(array_intersect(NurseAuthorization::catalog(), $data['test_names']));
            if ($authority && ($outside = array_diff($tests, $authority->permittedTests()))) {
                throw ValidationException::withMessages(['test_names' => "These tests are outside the doctor's authorization: ".implode(', ', $outside).'.']);
            }
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
                $entry->authorizing_doctor_id = $authority?->doctor_id ?? $user->id;
                $entry->followup_authorization_id = $grant?->id;
                $entry->nurse_authorization_id = $standing?->id;
                $entry->request_reason = $data['request_reason'];
                // The form token identifies the whole order; the first record keeps it so repeats are caught.
                $entry->order_reference = $data['submission_token'];
                $entry->submission_token = $ids ? (string) Str::uuid() : $data['submission_token'];
                $entry->lock_version = 1;
                $entry->save();
                $ids[] = $entry->id;
            }
            if ($grant) {
                $grant->used_at = now();
                $grant->save();
                $grant->clearNurseAlert();
            }
            activity('followups')->causedBy($user)->performedOn($entry)
                ->withProperties(['visit_id' => $visit->id, 'doctor_id' => $entry->authorizing_doctor_id, 'authorization_id' => $grant?->id, 'nurse_authorization_id' => $standing?->id, 'laboratory_record_ids' => $ids])->log('followup.requested');
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
        // Nurses may print only orders they placed; doctors and laboratory staff may print any order on the visit.
        abort_unless($isDoctor || $user->can('laboratory.view') || $records->first()->created_by === $user->id, 403);
        activity('followups')->causedBy($user)->performedOn($records->first())->withProperties(['visit_id' => $visit->id, 'laboratory_record_ids' => $records->pluck('id')->all()])->log('followup.slip_viewed');

        return response()->view('followups.slip', compact('patient', 'visit', 'records'))->header('Cache-Control', 'private, no-store');
    }
}
