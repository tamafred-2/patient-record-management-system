<?php

namespace App\Http\Controllers;

use App\Models\Dispensing;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\DispensingQuantities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DispensingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('pharmacy.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'prescription'])->whereHas('patient')->whereHas('prescription', fn ($q) => $q->where('status', 'ISSUED'))->whereDate('visit_date', $date)->latest('id')->paginate(20)->withQueryString();

        return view('pharmacy.index', compact('visits', 'date'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('pharmacy.view');
        $visit = $patient->visits()->findOrFail($visit);
        $prescription = $visit->prescription()->where('status', 'ISSUED')->with(['doctor', 'items', 'dispensings.items', 'dispensings.pharmacist'])->firstOrFail();
        $quantities = DispensingQuantities::forPrescription($prescription);

        return view('pharmacy.show', compact('patient', 'visit', 'prescription', 'quantities'));
    }

    public function store(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('pharmacy.view');
        Gate::authorize('pharmacy.dispense');
        $visit = $patient->visits()->findOrFail($visit);
        $rules = [
            'lock_version' => ['required', 'integer', 'min:1'],
            'confirm' => ['accepted'],
            'quantities' => ['required', 'array', 'min:1', 'max:20'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (['patient_id', 'visit_id', 'prescription_id', 'pharmacist_id', 'dispensed_at', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            // Match the visit -> prescription lock order used by the doctor workflow.
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $prescription = $visit->prescription()->where('status', 'ISSUED')->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can record a release.']);
            }
            if ($prescription->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'Another release changed the remaining quantities. Reload and review before submitting.']);
            }
            $prescription->load(['items', 'dispensings.items']);
            $remaining = DispensingQuantities::forPrescription($prescription);
            $items = [];
            foreach ($data['quantities'] as $id => $value) {
                if (! array_key_exists($id, $remaining)) {
                    throw ValidationException::withMessages(['quantities' => 'A selected medicine does not belong to this prescription.']);
                }
                $amount = DispensingQuantities::hundredths((string) ($value ?? '0'));
                if ($amount > $remaining[$id]['remaining']) {
                    throw ValidationException::withMessages(["quantities.$id" => 'Quantity exceeds the remaining prescribed amount.']);
                }
                if ($amount > 0) {
                    $items[] = ['prescription_item_id' => $id, 'quantity_dispensed' => DispensingQuantities::format($amount)];
                }
            }
            if (! $items) {
                throw ValidationException::withMessages(['quantities' => 'Enter a quantity greater than zero for at least one medicine.']);
            }
            $release = new Dispensing;
            $release->prescription_id = $prescription->id;
            $release->pharmacist_id = $request->user()->id;
            $release->dispensed_at = now();
            $release->remarks = $data['remarks'] ?? null;
            $release->save();
            $release->items()->createMany($items);
            $prescription->lock_version++;
            $prescription->save();
            activity('dispensings')->causedBy($request->user())->performedOn($release)->withProperties(['visit_id' => $visit->id, 'prescription_id' => $prescription->id, 'version' => $prescription->lock_version])->log('dispensing.recorded');
        });

        return redirect()->route('pharmacy.show', [$patient, $visit])->with('status', 'Medicine release recorded.');
    }
}
