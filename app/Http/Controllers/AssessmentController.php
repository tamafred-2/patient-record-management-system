<?php

namespace App\Http\Controllers;

use App\Models\NurseAuthorization;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Support\FollowupAccess;
use App\Support\VitalsAccess;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('vitals.view');
        $data = $request->validate(['authorized' => ['sometimes', 'boolean'], 'reassessment' => ['sometimes', 'boolean'], 'all' => ['sometimes', 'boolean'], 'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()], 'search' => ['nullable', 'string', 'max:100']]);
        $authorizedOnly = (bool) ($data['authorized'] ?? false);
        $reassessmentOnly = (bool) ($data['reassessment'] ?? false);
        $allDates = $authorizedOnly || $reassessmentOnly || (bool) ($data['all'] ?? false);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $search = trim($data['search'] ?? '');
        $pendingCount = Visit::whereHas('patient')->where('status', 'OPEN')->has('pendingAssessmentRequest')->count();
        $nurse = $request->user();
        $standing = FollowupAccess::nurse($nurse) && (bool) NurseAuthorization::activeFor($nurse)?->usable();
        $authorizedCount = FollowupAccess::nurse($nurse) ? Visit::whereHas('patient')->orderableBy($nurse, $standing)->count() : 0;
        $visits = Visit::with(['patient', 'service', 'vitalSign', 'pendingAssessmentRequest'])->whereHas('patient')
            ->withCount(['followupAuthorizations as authorized_followups_count' => fn ($query) => $query->availableTo($nurse)])
            ->when($authorizedOnly, fn ($query) => $query->orderableBy($nurse, $standing))
            ->when($reassessmentOnly, fn ($query) => $query->where('status', 'OPEN')->has('pendingAssessmentRequest'))
            ->when(! $allDates, fn ($query) => $query->whereDate('visit_date', $date))->searchPatientOrQueue($search)->priorityFirst()->scrollPaginate()->withQueryString();

        return view('assessments.index', compact('allDates', 'search', 'visits', 'date', 'pendingCount', 'reassessmentOnly', 'authorizedOnly', 'authorizedCount', 'standing'));
    }

    public function edit(Request $request, Patient $patient, string $visit)
    {
        $visit = $patient->visits()->with(['service', 'vitalSign.updater', 'pendingAssessmentRequest.requester'])->findOrFail($visit);
        abort_unless(VitalsAccess::canView($request->user(), $visit), 403);

        return view('assessments.edit', compact('patient', 'visit'));
    }

    public function save(Request $request, Patient $patient, string $visit)
    {
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        abort_unless(VitalsAccess::canRecord($request->user(), $visit, (bool) $visit->vitalSign), 403);
        $rules = [
            'lock_version' => ['required', 'integer', 'min:0'],
            'assessment_summary' => ['required', 'string', 'max:5000'],
            'complete_reassessment' => ['sometimes', 'accepted'],
            'reassessment_request_id' => ['nullable', 'required_with:complete_reassessment', 'integer', 'min:1'],
        ];
        foreach (['patient_id', 'visit_id', 'recorded_by', 'updated_by', 'recorded_at', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        foreach (VitalSign::FIELDS as $field) {
            $rules[$field] = ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999.99'];
        }
        foreach (['systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate'] as $field) {
            $rules[$field] = ['nullable', 'integer', 'min:1', 'max:32767'];
        }
        $rules['oxygen_saturation'] = ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'];
        $rules['temperature'] = ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999.99'];
        $rules['systolic_bp'][] = 'required_with:diastolic_bp';
        $rules['diastolic_bp'][] = 'required_with:systolic_bp';
        $data = $request->validate($rules);
        $measurements = array_intersect_key($data, array_flip(VitalSign::FIELDS));
        if (! count(array_filter($measurements, fn ($value) => $value !== null))) {
            throw ValidationException::withMessages(['measurements' => 'Enter at least one measurement.']);
        }
        DB::transaction(function () use ($request, $visit, $patient, $data, $measurements) {
            $lockedVisit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($lockedVisit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can have vital signs changed.']);
            }
            $record = $lockedVisit->vitalSign;
            abort_unless(VitalsAccess::canRecord($request->user(), $visit, (bool) $record), 403);
            if ((int) $data['lock_version'] !== ($record?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'Another user changed these measurements. Reload the page and review the latest values before saving.']);
            }
            $creating = ! $record;
            $pending = $lockedVisit->pendingAssessmentRequest;
            if (! empty($data['reassessment_request_id']) && (int) $data['reassessment_request_id'] !== $pending?->id) {
                throw ValidationException::withMessages(['reassessment' => 'The reassessment request changed. Reload the assessment before saving.']);
            }
            $record ??= new VitalSign;
            $record->fill($measurements);
            $record->assessment_summary = $data['assessment_summary'];
            if ($creating) {
                $record->visit_id = $visit->id;
                $record->recorded_by = $request->user()->id;
                $record->recorded_at = now();
            }
            $record->updated_by = $request->user()->id;
            $record->lock_version = (int) $data['lock_version'] + 1;
            $record->save();
            if ($request->boolean('complete_reassessment')) {
                $pending->status = 'COMPLETED';
                $pending->resolved_by = $request->user()->id;
                $pending->resolved_at = now();
                $pending->completed_version = $record->lock_version;
                $pending->save();
                activity('assessments')->causedBy($request->user())->performedOn($pending)
                    ->withProperties(['visit_id' => $visit->id, 'version' => $record->lock_version])->log('reassessment.completed');
            }
            activity('vitals')->causedBy($request->user())->performedOn($record)
                ->withProperties(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'version' => $record->lock_version])
                ->log($creating ? 'vitals.created' : 'vitals.updated');
            if ($creating) {
                WorkflowAlerts::send('vitals_ready', $lockedVisit, WorkflowAlerts::doctors(), $request->user());
            }
        });

        return redirect()->route('assessments.edit', [$patient, $visit])->with('status', 'Vital signs saved.');
    }
}
