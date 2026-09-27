<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Visit;
use App\Support\DispensingQuantities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VisitCompletionController extends Controller
{
    public function show(Patient $patient, string $visit)
    {
        $visit = $patient->visits()->with(['service', 'completedBy'])->findOrFail($visit);
        Gate::authorize('complete', $visit);
        $review = $this->review($visit);

        return view('visits.complete', compact('patient', 'visit', 'review'));
    }

    public function store(Request $request, Patient $patient, string $visit)
    {
        $visit = $patient->visits()->findOrFail($visit);
        Gate::authorize('complete', $visit);
        $data = $request->validate([
            'confirm' => ['accepted'],
            'review_version' => ['required', 'string', 'size:64'],
            'completion_remarks' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'], 'completed_by' => ['prohibited'], 'completed_at' => ['prohibited'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('complete', $visit);
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'This visit is already closed.']);
            }
            $review = $this->review($visit);
            if (! hash_equals($review['version'], $data['review_version'])) {
                throw ValidationException::withMessages(['visit' => 'Visit records changed. Reload and review the latest service status before completing.']);
            }
            if ($review['blocked']) {
                throw ValidationException::withMessages(['visit' => 'Issue the prescription draft and resolve pending laboratory services before completing.']);
            }
            if ($review['unreleased'] && trim($data['completion_remarks'] ?? '') === '') {
                throw ValidationException::withMessages(['completion_remarks' => 'Explain why the visit is ending with unreleased medicines, after checking with Pharmacy.']);
            }
            $visit->status = 'COMPLETED';
            $visit->completed_at = now();
            $visit->completed_by = $request->user()->id;
            $visit->completion_remarks = $data['completion_remarks'] ?? null;
            $visit->save();
            activity('visits')->causedBy($request->user())->performedOn($visit)
                ->withProperties(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'status' => 'COMPLETED'])
                ->log('visit.completed');
        });

        return redirect()->route('visits.completion', [$patient, $visit])->with('status', 'Visit completed.');
    }

    private function review(Visit $visit): array
    {
        $visit->load(['prescription.items', 'prescription.dispensings.items', 'laboratoryRecords', 'treatmentRecord', 'vitalSign', 'eligibilityCheck', 'vaccinationRecords', 'midwifeCareRecords']);
        $prescription = $visit->prescription;
        $unreleased = $prescription?->status === 'ISSUED'
            && collect(DispensingQuantities::forPrescription($prescription))->contains(fn ($line) => $line['remaining'] > 0);
        $pendingLabs = $visit->laboratoryRecords->where('availability_status', 'PENDING')->count();
        $draft = $prescription?->status === 'DRAFT';
        $version = hash('sha256', json_encode([
            $visit->id, $visit->status, $prescription?->id, $prescription?->lock_version,
            $visit->laboratoryRecords->sortBy('id')->map->only(['id', 'availability_status', 'lock_version'])->values()->all(),
            $visit->treatmentRecord?->lock_version, $visit->vitalSign?->lock_version, $visit->eligibilityCheck?->lock_version,
            $visit->dispositionRecords()->count(),
            $visit->midwifeCareRecords->sortBy('id')->map->only(['id', 'lock_version'])->values()->all(),
            $visit->vaccinationRecords->sortBy('id')->map->only(['id', 'lock_version'])->values()->all(),
        ]));

        return ['midwifeCareCount' => $visit->midwifeCareRecords->count(), 'vaccinationCount' => $visit->vaccinationRecords->count(), 'version' => $version, 'draft' => $draft, 'unreleased' => $unreleased,
            'pendingLabs' => $pendingLabs, 'blocked' => $draft || $pendingLabs > 0,
            'pharmacy' => ! $prescription ? 'No prescription recorded' : ($draft ? 'Draft: not yet sent to Pharmacy' : ($unreleased ? 'Issued: medicines remain unreleased' : 'Issued: all prescribed quantities released'))];
    }
}
