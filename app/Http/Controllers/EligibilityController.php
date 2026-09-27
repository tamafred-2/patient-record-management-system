<?php

namespace App\Http\Controllers;

use App\Models\EligibilityCheck;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EligibilityController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('eligibility.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $visits = Visit::with(['patient', 'service', 'eligibilityCheck'])->whereHas('patient')->whereDate('visit_date', $date)->searchPatientOrQueue($search)->latest('id')->paginate(20)->withQueryString();

        return view('eligibility.index', compact('visits', 'date', 'search'));
    }

    public function edit(Patient $patient, string $visit)
    {
        Gate::authorize('eligibility.view');
        $visit = $patient->visits()->with(['service', 'eligibilityCheck.verifier', 'eligibilityCheck.editor'])->findOrFail($visit);

        return view('eligibility.edit', compact('patient', 'visit'));
    }

    public function save(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('eligibility.view');
        Gate::authorize('eligibility.verify');
        $visit = $patient->visits()->findOrFail($visit);
        $data = $request->validate([
            'philhealth_confirmed' => ['required', 'boolean'],
            'status' => ['prohibited'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'],
            'verified_by' => ['prohibited'], 'verified_at' => ['prohibited'],
            'updated_by' => ['prohibited'], 'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $lockedVisit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($lockedVisit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Verification can only be changed for an open visit.']);
            }
            $record = $lockedVisit->eligibilityCheck;
            if ((int) $data['lock_version'] !== ($record?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'Another user changed this record. Reload the latest result before saving your changes.']);
            }
            $creating = ! $record;
            $record ??= new EligibilityCheck;
            $record->fill(['philhealth_confirmed' => $data['philhealth_confirmed'], 'remarks' => $data['remarks'] ?? null]);
            // Preserve legacy text verbatim; it is never interpreted as confirmation.
            if ($creating) {
                $record->status = 'CHECKBOX';
            }
            if ($creating) {
                $record->visit_id = $visit->id;
                $record->verified_by = $request->user()->id;
                $record->verified_at = now();
            }
            $record->updated_by = $request->user()->id;
            $record->lock_version = (int) $data['lock_version'] + 1;
            $record->save();
            activity('eligibility')->causedBy($request->user())->performedOn($record)
                ->withProperties(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'version' => $record->lock_version])
                ->log($creating ? 'eligibility.created' : 'eligibility.updated');
        });

        return redirect()->route('eligibility.edit', [$patient, $visit])->with('status', 'PhilHealth confirmation saved.');
    }
}
