<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Support\ListFilters;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LaboratoryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('laboratory.view');
        $data = $request->validate(['all' => ['sometimes', 'boolean'], 'date' => ListFilters::pastDate(), 'search' => ListFilters::search(), 'record' => ['nullable', Rule::in(['all', 'with_records'])]]);
        $allDates = (bool) ($data['all'] ?? false);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $search = trim($data['search'] ?? '');
        $record = $data['record'] ?? 'all';
        $visits = Visit::forLaboratory()->with(['patient', 'service'])->withCount('laboratoryRecords')->whereHas('patient')->when($record === 'with_records', fn ($query) => $query->whereHas('laboratoryRecords'))->when(! $allDates, fn ($query) => $query->whereDate('visit_date', $date))->searchPatientOrQueue($search)->priorityFirst()->scrollPaginate()->withQueryString();

        return view('laboratory.index', compact('allDates', 'search', 'date', 'visits', 'record'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('laboratory.view');
        $visit = $patient->visits()->forLaboratory()->with('service')->findOrFail($visit);
        $records = $visit->laboratoryRecords()->with(['authorizingDoctor', 'requester', 'followupAuthorization.doctor', 'nurseAuthorization'])->latest('id')->scrollPaginate();

        return view('laboratory.show', compact('patient', 'visit', 'records'));
    }

    public function edit(Patient $patient, string $visit, string $record)
    {
        Gate::authorize('laboratory.view');
        $visit = $patient->visits()->forLaboratory()->findOrFail($visit);
        $record = $visit->laboratoryRecords()->findOrFail($record);

        return view('laboratory.edit', compact('patient', 'visit', 'record'));
    }

    public function save(Request $request, Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('laboratory.view');
        Gate::authorize('laboratory.record');
        $visit = $patient->visits()->forLaboratory()->findOrFail($visit);
        $existing = $record === null ? null : $visit->laboratoryRecords()->findOrFail($record);
        $tests = array_merge(...array_values(config('laboratory.tests')));
        if ($existing && ! in_array($existing->test_name, $tests, true)) {
            $tests[] = $existing->test_name;
        }
        // Unreported template components must not become fabricated results.
        $rows = $request->input('result_rows');
        if (is_array($rows) && count($rows) <= 30) {
            $request->merge(['result_rows' => array_values(array_filter($rows, function ($row) {
                if (! is_array($row)) {
                    return true;
                }
                foreach (['value', 'unit', 'reference_range', 'flag'] as $key) {
                    $value = $row[$key] ?? '';
                    if (! is_string($value) || trim($value) !== '') {
                        return true;
                    }
                }

                return false;
            }))]);
        }
        $rules = [
            'test_name' => ['required', 'string', Rule::in($tests)],
            'availability_status' => ['required', Rule::in($record === null ? ['PENDING', 'EXTERNAL_ADVISED'] : array_keys(LaboratoryRecord::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'external_advice' => ['nullable', 'required_if:availability_status,EXTERNAL_ADVISED', 'prohibited_unless:availability_status,EXTERNAL_ADVISED', 'string', 'max:1000'],
            'result' => ['prohibited'],
            'result_rows' => ['nullable', 'required_if:availability_status,PERFORMED_RHU', 'prohibited_unless:availability_status,PERFORMED_RHU', 'array', 'min:1', 'max:30'],
            'result_rows.*' => ['array:measurement,value,unit,reference_range,flag'],
            'result_rows.*.measurement' => ['required', 'string', 'max:150'],
            'result_rows.*.value' => ['required', 'string', 'max:500'],
            'result_rows.*.unit' => ['nullable', 'string', 'max:50'],
            'result_rows.*.reference_range' => ['nullable', 'string', 'max:200'],
            'result_rows.*.flag' => ['nullable', Rule::in(['Normal', 'High', 'Low', 'Abnormal'])],
            'specimen' => ['nullable', 'prohibited_unless:availability_status,PERFORMED_RHU', 'string', 'max:200'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'submission_token' => $record === null ? ['required', 'uuid'] : ['prohibited'],
        ];
        foreach (['patient_id', 'visit_id', 'created_by', 'updated_by', 'performed_by', 'performed_at', 'completed_at', 'result_notes', 'authorizing_doctor_id', 'followup_authorization_id', 'request_reason'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $patient, $visit, $record, $data) {
            $visit = $patient->visits()->forLaboratory()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if (! $visit->isOpen()) {
                throw ValidationException::withMessages(['visit' => 'Only open visits can have laboratory records changed.']);
            }
            $entry = $record === null ? null : $visit->laboratoryRecords()->findOrFail($record);
            if ($entry?->authorizing_doctor_id && $entry->test_name !== $data['test_name']) {
                throw ValidationException::withMessages(['test_name' => 'The authorized test cannot be changed. Ask the doctor to review the request.']);
            }
            if ((int) $data['lock_version'] !== ($entry?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'This record changed. Reload and review the latest values.']);
            }
            if (! $entry && LaboratoryRecord::where('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['submission_token' => 'This submission was already saved. Review the visit records before adding another.']);
            }
            $creating = ! $entry;
            $wasPending = $entry?->availability_status === 'PENDING';
            $entry ??= new LaboratoryRecord;
            if ($creating) {
                $entry->visit_id = $visit->id;
                $entry->created_by = $request->user()->id;
                $entry->submission_token = $data['submission_token'];
            }
            if ($entry->exists && $entry->availability_status === 'PERFORMED_RHU' && $data['availability_status'] !== 'PERFORMED_RHU') {
                throw ValidationException::withMessages(['availability_status' => 'Completed records can have results corrected, but cannot return to pending or external advice.']);
            }
            $entry->test_name = $data['test_name'];
            $entry->availability_status = $data['availability_status'];
            $entry->notes = $data['notes'] ?? null;
            $entry->external_advice = $data['availability_status'] === 'EXTERNAL_ADVISED' ? $data['external_advice'] : null;
            $entry->result_rows = $data['availability_status'] === 'PERFORMED_RHU' ? $data['result_rows'] : null;
            $entry->specimen = $data['availability_status'] === 'PERFORMED_RHU' ? ($data['specimen'] ?? null) : null;
            if ($data['availability_status'] === 'PERFORMED_RHU') {
                $entry->completed_at ??= now();
            }
            $entry->updated_by = $request->user()->id;
            $entry->lock_version = (int) $data['lock_version'] + 1;
            $entry->save();
            activity('laboratory')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $visit->id, 'version' => $entry->lock_version])->log($creating ? 'laboratory.created' : 'laboratory.updated');
            // The ordering doctor hears once, when a pending request is completed or referred out.
            if ($wasPending && $entry->availability_status !== 'PENDING' && $entry->authorizing_doctor_id) {
                WorkflowAlerts::send('lab_completed', $visit, [User::find($entry->authorizing_doctor_id)], $request->user(), ['laboratory_record_ids' => [$entry->id]]);
            }
        });

        return redirect()->route('laboratory.show', [$patient, $visit])->with('status', 'Laboratory service record saved.');
    }
}
