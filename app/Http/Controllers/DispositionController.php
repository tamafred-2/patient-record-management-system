<?php

namespace App\Http\Controllers;

use App\Models\DispositionRecord;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DispositionController extends Controller
{
    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('dispositions.view');
        $visit = $patient->visits()->findOrFail($visit);
        $records = $visit->dispositionRecords()->latest('id')->paginate(15);

        return view('dispositions.show', compact('patient', 'visit', 'records'));
    }

    public function store(Request $request, Patient $patient, string $visit)
    {
        Gate::authorize('dispositions.view');
        Gate::authorize('dispositions.record');
        $visit = $patient->visits()->findOrFail($visit);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(DispositionRecord::TYPES))],
            'reason' => ['required', 'string', 'max:3000'],
            'facility_name' => ['nullable', 'prohibited_if:type,CERTIFICATE_REQUEST', 'string', 'max:200'],
            'specialty' => ['nullable', 'prohibited_if:type,CERTIFICATE_REQUEST', 'string', 'max:200'],
            'remarks' => ['nullable', 'string', 'max:3000'],
            'submission_token' => ['required', 'uuid'],
            'confirm' => ['accepted'],
            'visit_id' => ['prohibited'], 'patient_id' => ['prohibited'], 'created_by' => ['prohibited'], 'issued_at' => ['prohibited'], 'findings' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $data) {
            $visit = $patient->visits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can receive new records.']);
            }
            if (DispositionRecord::where('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['submission_token' => 'Already recorded. Reload the history before adding another entry.']);
            }
            $entry = new DispositionRecord;
            foreach (['type', 'reason', 'facility_name', 'specialty', 'remarks', 'submission_token'] as $key) {
                $entry->$key = $data[$key] ?? null;
            }
            $entry->visit_id = $visit->id;
            $entry->created_by = $request->user()->id;
            $entry->save();
            activity('dispositions')->causedBy($request->user())->performedOn($entry)->withProperties(['visit_id' => $visit->id, 'type' => $entry->type])->log('disposition.recorded');
        });

        return redirect()->route('dispositions.show', [$patient, $visit])->with('status', 'Supporting record saved.');
    }
}
