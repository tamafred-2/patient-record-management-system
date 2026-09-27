<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Prescription;
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

        return view('prescriptions.show', compact('patient', 'visit'));
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
            'items.*.quantity_prescribed' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
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
                $record->status = 'DRAFT';
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
            $record->status = 'ISSUED';
            $record->prescribed_at = now();
            $record->lock_version++;
            $record->save();
            activity('prescriptions')->causedBy($request->user())->performedOn($record)->withProperties(['visit_id' => $visit->id, 'version' => $record->lock_version, 'status' => 'ISSUED'])->log('prescription.issued');
        });

        return redirect()->route('prescriptions.show', [$patient, $visit])->with('status', 'Prescription issued and available in Pharmacy. The pharmacist can check availability and record medicines released.');
    }

    private function ensureWritable($visit, ?Prescription $record, Request $request): void
    {
        abort_if($record && $record->doctor_id !== $request->user()->id, 403);
        if ($visit->status !== 'OPEN' || ! $visit->treatmentRecord || ($record && $record->status !== 'DRAFT')) {
            throw ValidationException::withMessages(['prescription' => 'Only drafts for open visits with a saved ITR can be changed.']);
        }
    }
}
