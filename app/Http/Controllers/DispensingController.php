<?php

namespace App\Http\Controllers;

use App\Models\Dispensing;
use App\Models\DispensingItem;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\DispensingQuantities;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispensingController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('pharmacy.view');
        $data = $request->validate(['all' => ['sometimes', 'boolean'], 'date' => ListFilters::pastDate(), 'search' => ListFilters::search()]);
        $allDates = (bool) ($data['all'] ?? false);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $search = trim($data['search'] ?? '');
        $visits = Visit::with(['patient', 'prescription'])->whereHas('patient')->whereHas('prescription', fn ($q) => $q->issued())->when(! $allDates, fn ($query) => $query->whereDate('visit_date', $date))->searchPatientOrQueue($search)->priorityFirst()->scrollPaginate()->withQueryString();

        return view('pharmacy.index', compact('allDates', 'search', 'visits', 'date'));
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('pharmacy.view');
        $visit = $patient->visits()->findOrFail($visit);
        $prescription = $visit->prescription()->issued()->with(['doctor', 'items', 'dispensings.items', 'dispensings.pharmacist'])->firstOrFail();
        $quantities = DispensingQuantities::forPrescription($prescription);

        return view('pharmacy.show', compact('patient', 'visit', 'prescription', 'quantities'));
    }

    public function void(Request $request, Patient $patient, string $visit, string $dispensing)
    {
        Gate::authorize('pharmacy.view');
        Gate::authorize('pharmacy.dispense');
        $data = $request->validate([
            'confirm' => ['accepted'], 'reason' => ['required', 'string', 'max:1000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $dispensing, $data) {
            $visit = $patient->visits()->whereKey($visit)->lockForUpdate()->firstOrFail();
            $prescription = $visit->prescription()->issued()->lockForUpdate()->firstOrFail();
            $release = $prescription->dispensings()->whereKey($dispensing)->lockForUpdate()->firstOrFail();
            if (! $visit->isOpen()) {
                throw ValidationException::withMessages(['visit' => 'Reopen the visit before correcting a release.']);
            }
            if ($prescription->lock_version !== (int) $data['lock_version'] || $release->voided_at) {
                throw ValidationException::withMessages(['lock_version' => 'Release history changed. Reload before correcting.']);
            }
            $release->forceFill(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $data['reason']])->save();
            $prescription->lock_version++;
            $prescription->save();
            activity('dispensings')->causedBy($request->user())->performedOn($release)
                ->withProperties(['visit_id' => $visit->id, 'prescription_id' => $prescription->id, 'version' => $prescription->lock_version])->log('dispensing.voided');
        });

        return redirect()->route('pharmacy.show', [$patient, $visit])->with('status', 'Incorrect release voided. Record the correct quantities if needed.');
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
            'released_as' => ['nullable', 'array', 'max:20'],
            'released_as.*' => ['nullable', 'string', 'max:150'],
            'outcomes' => ['nullable', 'array', 'max:20'],
            'outcomes.*' => ['nullable', Rule::in(['KEEP', ...array_keys(DispensingItem::OUTCOMES)])],
            'outcome_notes' => ['nullable', 'array', 'max:20'],
            'outcome_notes.*' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (['patient_id', 'visit_id', 'prescription_id', 'pharmacist_id', 'dispensed_at', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        $data = $request->validate($rules);
        $released = DB::transaction(function () use ($request, $patient, $visit, $data) {
            // Match the visit -> prescription lock order used by the doctor workflow.
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            $prescription = $visit->prescription()->issued()->lockForUpdate()->firstOrFail();
            if (! $visit->isOpen()) {
                throw ValidationException::withMessages(['visit' => 'Only open visits can record a release.']);
            }
            if ($prescription->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'Another release changed the remaining quantities. Reload and review before submitting.']);
            }
            $prescription->load(['items', 'dispensings.items']);
            $remaining = DispensingQuantities::forPrescription($prescription);
            $ids = array_unique(array_merge(array_keys($data['quantities']), array_keys($data['released_as'] ?? []), array_keys($data['outcomes'] ?? []), array_keys($data['outcome_notes'] ?? [])));
            $items = [];
            foreach ($ids as $id) {
                if (! array_key_exists($id, $remaining)) {
                    throw ValidationException::withMessages(['quantities' => 'A selected medicine does not belong to this prescription.']);
                }
                $amount = DispensingQuantities::hundredths((string) ($data['quantities'][$id] ?? '0'));
                $releasedAs = trim($data['released_as'][$id] ?? '') ?: null;
                $outcome = ($data['outcomes'][$id] ?? 'KEEP') === 'KEEP' ? null : $data['outcomes'][$id];
                $note = trim($data['outcome_notes'][$id] ?? '') ?: null;
                if ($amount > $remaining[$id]['remaining']) {
                    throw ValidationException::withMessages(["quantities.$id" => 'Quantity exceeds the remaining prescribed amount.']);
                }
                if ($releasedAs !== null && $amount === 0) {
                    throw ValidationException::withMessages(["released_as.$id" => 'Enter the quantity released, or clear "Released as".']);
                }
                // An outcome closes whatever this entry does not release.
                $closed = $outcome !== null ? $remaining[$id]['remaining'] - $amount : 0;
                if ($outcome !== null && $closed === 0) {
                    throw ValidationException::withMessages(["outcomes.$id" => 'Nothing remains after this release. Keep "Remaining quantity" as is.']);
                }
                if ($note !== null && $outcome === null) {
                    throw ValidationException::withMessages(["outcome_notes.$id" => 'Choose buy outside or declined for the remaining quantity, or clear the note.']);
                }
                if ($amount > 0 || $closed > 0) {
                    $items[] = ['prescription_item_id' => $id, 'quantity_dispensed' => DispensingQuantities::format($amount), 'released_as' => $releasedAs,
                        'remainder_outcome' => $outcome, 'quantity_closed' => DispensingQuantities::format($closed), 'outcome_note' => $note];
                }
            }
            if (! $items) {
                throw ValidationException::withMessages(['quantities' => 'Enter a quantity greater than zero, or choose buy outside or declined, for at least one medicine.']);
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

            return collect($items)->contains(fn ($item) => $item['quantity_dispensed'] !== '0.00');
        });

        return redirect()->route('pharmacy.show', [$patient, $visit])->with('status', $released ? 'Medicine release recorded.' : 'Pharmacy outcome recorded. No medicine was released in this entry.');
    }
}
