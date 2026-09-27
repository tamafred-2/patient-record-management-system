<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Models\VitalSign;
use App\Services\DiagnosisLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TreatmentRecordController extends Controller
{
    public function edit(Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->with(['service', 'vitalSign', 'treatmentRecord'])->findOrFail($visit);

        $diagnosisSuggestions = app(DiagnosisLabels::class)->suggestions();

        return view('consultations.itr', compact('patient', 'visit', 'diagnosisSuggestions'));
    }

    public function save(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('consultations.view');
        $visit = $patient->visits()->findOrFail($visit);
        Gate::authorize($visit->treatmentRecord ? 'itr.update' : 'itr.create');
        $rules = [
            'lock_version' => ['required', 'integer', 'min:0'],
            'vitals_version' => ['required', 'integer', 'min:0'],
            'lmp' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$visit->visit_date->toDateString()],
            'diagnoses' => ['nullable', 'string', 'max:1600'],
            'medicine_details' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (TreatmentRecord::QUESTIONS as $field => $label) {
            $rules[$field] = ['nullable', 'boolean'];
        }
        foreach (['assessment', 'planning', 'remarks'] as $field) {
            $rules[$field] = ['nullable', 'string', 'max:5000'];
        }
        foreach (['patient_id', 'visit_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'objective_snapshot', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        $diagnoses = [];
        foreach (preg_split('/\R/u', $data['diagnoses'] ?? '') as $label) {
            $label = trim(preg_replace('/\s+/u', ' ', $label));
            if ($label === '') {
                continue;
            }
            if (mb_strlen($label) > 150) {
                throw ValidationException::withMessages(['diagnoses' => 'Each diagnosis must be no more than 150 characters.']);
            }
            $diagnoses[DiagnosisLabels::normalize($label)] = $label;
        }
        if (count($diagnoses) > 10) {
            throw ValidationException::withMessages(['diagnoses' => 'Enter no more than 10 diagnoses, one per line.']);
        }
        $fields = array_merge(array_keys(TreatmentRecord::QUESTIONS), ['lmp', 'medicine_details', 'assessment', 'planning', 'remarks']);
        $entries = array_intersect_key($data, array_flip($fields));
        if (! $diagnoses && ! count(array_filter($entries, fn ($v) => $v !== null && $v !== ''))) {
            throw ValidationException::withMessages(['record' => 'Enter at least one ITR response or note.']);
        }
        if (! empty($data['medicine_details']) && (string) ($data['taking_medicine'] ?? '') !== '1') {
            throw ValidationException::withMessages(['medicine_details' => 'Select Yes for Currently Taking Medicine when entering medicine details.']);
        }
        if (! empty($data['lmp']) && $patient->birth_date && $data['lmp'] < $patient->birth_date->toDateString()) {
            throw ValidationException::withMessages(['lmp' => 'LMP cannot be before the patient birth date.']);
        }
        DB::transaction(function () use ($request, $patient, $visit, $data, $fields, $diagnoses) {
            $lockedVisit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $record = $lockedVisit->treatmentRecord;
            Gate::authorize($record ? 'itr.update' : 'itr.create');
            if ($lockedVisit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'ITR editing is available only for open visits.']);
            }
            if ((int) $data['lock_version'] !== ($record?->lock_version ?? 0) || (int) $data['vitals_version'] !== ($lockedVisit->vitalSign?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'The ITR or vital signs changed. Reload and review the latest values before saving.']);
            }
            $creating = ! $record;
            $record ??= new TreatmentRecord;
            foreach ($fields as $field) {
                $record->$field = $data[$field] ?? null;
            }
            if ($creating) {
                $record->visit_id = $visit->id;
                $record->created_by = $request->user()->id;
            }
            if (array_key_exists('diagnoses', $data)) {
                $record->diagnoses = array_values($diagnoses);
            }
            $record->updated_by = $request->user()->id;
            $record->lock_version = (int) $data['lock_version'] + 1;
            $record->objective_snapshot = $lockedVisit->vitalSign?->only(array_merge(VitalSign::FIELDS, ['recorded_at', 'updated_at', 'lock_version']));
            $record->save();
            activity('itr')->causedBy($request->user())->performedOn($record)
                ->withProperties(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'version' => $record->lock_version])
                ->log($creating ? 'itr.created' : 'itr.updated');
        });

        return redirect()->route('itr.edit', [$patient, $visit])->with('status', 'ITR saved.');
    }
}
