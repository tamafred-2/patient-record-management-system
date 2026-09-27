<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Visit;
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
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service'])->withCount('laboratoryRecords')->whereHas('patient')->whereDate('visit_date', $date)->latest('id')->paginate(20)->withQueryString();

        return view('laboratory.index', compact('date', 'visits'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('laboratory.view');
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        $records = $visit->laboratoryRecords()->latest('id')->paginate(15);

        return view('laboratory.show', compact('patient', 'visit', 'records'));
    }

    public function edit(Patient $patient, string $visit, string $record)
    {
        Gate::authorize('laboratory.view');
        $visit = $patient->visits()->findOrFail($visit);
        $record = $visit->laboratoryRecords()->findOrFail($record);

        return view('laboratory.edit', compact('patient', 'visit', 'record'));
    }

    public function save(Request $request, Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('laboratory.view');
        Gate::authorize('laboratory.record');
        $visit = $patient->visits()->findOrFail($visit);
        if ($record !== null) {
            $visit->laboratoryRecords()->findOrFail($record);
        }
        $rules = [
            'test_name' => ['required', 'string', 'max:200'],
            'availability_status' => ['required', Rule::in(array_keys(LaboratoryRecord::STATUSES))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'external_advice' => ['nullable', 'required_if:availability_status,EXTERNAL_ADVISED', 'prohibited_unless:availability_status,EXTERNAL_ADVISED', 'string', 'max:1000'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'submission_token' => $record === null ? ['required', 'uuid'] : ['prohibited'],
        ];
        foreach (['patient_id', 'visit_id', 'created_by', 'updated_by', 'performed_by', 'performed_at', 'result', 'result_notes'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $patient, $visit, $record, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can have laboratory records changed.']);
            }
            $entry = $record === null ? null : $visit->laboratoryRecords()->findOrFail($record);
            if ((int) $data['lock_version'] !== ($entry?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'This record changed. Reload and review the latest values.']);
            }
            if (! $entry && LaboratoryRecord::where('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['submission_token' => 'This submission was already saved. Review the visit records before adding another.']);
            }
            $creating = ! $entry;
            $entry ??= new LaboratoryRecord;
            if ($creating) {
                $entry->visit_id = $visit->id;
                $entry->created_by = $request->user()->id;
                $entry->submission_token = $data['submission_token'];
            }
            $entry->test_name = $data['test_name'];
            $entry->availability_status = $data['availability_status'];
            $entry->notes = $data['notes'] ?? null;
            $entry->external_advice = $data['availability_status'] === 'EXTERNAL_ADVISED' ? $data['external_advice'] : null;
            $entry->updated_by = $request->user()->id;
            $entry->lock_version = (int) $data['lock_version'] + 1;
            $entry->save();
            activity('laboratory')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $visit->id, 'version' => $entry->lock_version])->log($creating ? 'laboratory.created' : 'laboratory.updated');
        });

        return redirect()->route('laboratory.show', [$patient, $visit])->with('status', 'Laboratory service record saved.');
    }
}
