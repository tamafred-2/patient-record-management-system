<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use App\Models\VitalSign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('vitals.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service', 'vitalSign'])->whereHas('patient')->whereDate('visit_date', $date)->latest('id')->paginate(20)->withQueryString();

        return view('assessments.index', compact('visits', 'date'));
    }

    public function edit(Patient $patient, string $visit)
    {
        Gate::authorize('vitals.view');
        $visit = $patient->visits()->with(['service', 'vitalSign'])->findOrFail($visit);

        return view('assessments.edit', compact('patient', 'visit'));
    }

    public function save(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('vitals.view');
        $visit = $patient->visits()->findOrFail($visit);
        Gate::authorize($visit->vitalSign ? 'vitals.update' : 'vitals.create');
        $rules = ['lock_version' => ['required', 'integer', 'min:0']];
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
            Gate::authorize($record ? 'vitals.update' : 'vitals.create');
            if ((int) $data['lock_version'] !== ($record?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'Another user changed these measurements. Reload the page and review the latest values before saving.']);
            }
            $creating = ! $record;
            $record ??= new VitalSign;
            $record->fill($measurements);
            if ($creating) {
                $record->visit_id = $visit->id;
                $record->recorded_by = $request->user()->id;
                $record->recorded_at = now();
            }
            $record->updated_by = $request->user()->id;
            $record->lock_version = (int) $data['lock_version'] + 1;
            $record->save();
            activity('vitals')->causedBy($request->user())->performedOn($record)
                ->withProperties(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'version' => $record->lock_version])
                ->log($creating ? 'vitals.created' : 'vitals.updated');
        });

        return redirect()->route('assessments.edit', [$patient, $visit])->with('status', 'Vital signs saved.');
    }
}
