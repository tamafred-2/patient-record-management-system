<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Prescription;
use App\Support\WorkflowAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PrescriptionController extends Controller
{
    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('prescriptions.view');
        $visit = $patient->visits()->with(['treatmentRecord', 'prescription.items', 'prescription.doctor'])->findOrFail($visit);

        $suggestions = [];
        // Entry shortcuts only: no medicine-specific recommendations or default selection.
        $presets = [
            'strength' => ['5 mg', '10 mg', '20 mg', '50 mg', '100 mg', '250 mg', '500 mg', '1 g'],
            'dosage' => ['1 tablet', '2 tablets', '1 capsule', '2 capsules', '2.5 mL', '5 mL', '10 mL'],
            'frequency' => ['Once a day', '2 times a day', '3 times a day', '4 times a day', 'Once a week'],
            'duration' => ['1 day', '3 days', '5 days', '7 days', '1 week', '2 weeks', '1 month'],
        ];
        foreach (['strength', 'dosage', 'frequency', 'duration'] as $field) {
            $suggestions[$field] = DB::table('prescription_items')
                ->join('prescriptions', 'prescriptions.id', '=', 'prescription_items.prescription_id')
                ->join('visits', 'visits.id', '=', 'prescriptions.visit_id')
                ->join('patients', 'patients.id', '=', 'visits.patient_id')
                ->where('prescriptions.doctor_id', auth()->id())
                ->whereNull('patients.deleted_at')
                ->whereNotNull('prescription_items.'.$field)
                ->where('prescription_items.'.$field, '!=', '')
                ->distinct()->orderBy('prescription_items.'.$field)->limit(100)
                ->pluck('prescription_items.'.$field);
            $suggestions[$field] = collect($presets[$field])->merge($suggestions[$field])->unique()->values();
        }

        return view('prescriptions.show', compact('patient', 'visit', 'suggestions'));
    }

    public function save(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('prescriptions.view');
        $visit = $patient->visits()->findOrFail($visit);
        Gate::authorize($visit->prescription ? 'prescriptions.update' : 'prescriptions.create');
        $rules = [
            'lock_version' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:medicine_name,strength,dosage,frequency,duration,instructions,quantity_prescribed'],
            'items.*.medicine_name' => ['required', 'string', 'max:200'],
            'items.*.strength' => ['nullable', 'string', 'max:100'],
            'items.*.dosage' => ['required', 'string', 'max:200'],
            'items.*.frequency' => ['required', 'string', 'max:200'],
            'items.*.duration' => ['nullable', 'string', 'max:200'],
            'items.*.instructions' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity_prescribed' => ['required', 'integer', 'min:1', 'max:99999999'],
        ];
        foreach (['patient_id', 'visit_id', 'treatment_record_id', 'doctor_id', 'status', 'prescribed_at', 'prescription_number'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $record = $visit->prescription;
            Gate::authorize($record ? 'prescriptions.update' : 'prescriptions.create');
            $this->ensureWritable($visit, $record, $request);
            if ((int) $data['lock_version'] !== ($record?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'Prescription changed. Reload and review the latest draft.']);
            }
            $creating = ! $record;
            $record ??= new Prescription;
            if ($creating) {
                $record->visit_id = $visit->id;
                $record->treatment_record_id = $visit->treatmentRecord->id;
                $record->doctor_id = $request->user()->id;
                $record->status = Prescription::DRAFT;
            }
            $record->lock_version = (int) $data['lock_version'] + 1;
            $record->save();
            if ($creating) {
                $record->prescription_number = 'RX-'.str_pad((string) $record->id, 6, '0', STR_PAD_LEFT);
                $record->save();
            }
            $record->items()->delete();
            $record->items()->createMany($data['items']);
            activity('prescriptions')->causedBy($request->user())->performedOn($record)->withProperties(['visit_id' => $visit->id, 'version' => $record->lock_version, 'status' => 'DRAFT'])->log($creating ? 'prescription.created' : 'prescription.updated');
        });

        return redirect()->route('prescriptions.show', [$patient, $visit])->with('status', 'Prescription draft saved. Review it before issuing.');
    }

    public function issue(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('prescriptions.view');
        Gate::authorize('prescriptions.update');
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1'], 'confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $record = $visit->prescription()->firstOrFail();
            $this->ensureWritable($visit, $record, $request);
            if ($record->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'Prescription changed. Reload and review it before issuing.']);
            }
            if (! $record->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Add at least one medicine.']);
            }
            $record->status = Prescription::ISSUED;
            $record->prescribed_at = now();
            $record->lock_version++;
            $record->save();
            activity('prescriptions')->causedBy($request->user())->performedOn($record)->withProperties(['visit_id' => $visit->id, 'version' => $record->lock_version, 'status' => 'ISSUED'])->log('prescription.issued');
            WorkflowAlerts::send('prescription_issued', $visit, WorkflowAlerts::pharmacists(), $request->user());
        });

        return redirect()->route('prescriptions.show', [$patient, $visit])->with('status', 'Prescription issued and available in Pharmacy. The pharmacist can check availability and record medicines released.');
    }

    private function ensureWritable($visit, ?Prescription $record, Request $request): void
    {
        abort_if($record && $record->doctor_id !== $request->user()->id, 403);
        if (! $visit->isOpen() || ! $visit->treatmentRecord || ($record && $record->status !== Prescription::DRAFT)) {
            throw ValidationException::withMessages(['prescription' => 'Only drafts for open visits with a saved ITR can be changed.']);
        }
    }
}
