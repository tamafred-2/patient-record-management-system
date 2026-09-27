<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\VaccinationRecord;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VaccinationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('vaccinations.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'search' => ['nullable', 'string', 'max:100']]);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $search = trim($data['search'] ?? '');
        $visits = Visit::vaccinationVisits()->with('patient')->withCount('vaccinationRecords')
            ->whereDate('visit_date', $date)->searchPatientOrQueue($search)->latest('id')->paginate(20)->withQueryString();

        return view('vaccinations.index', compact('date', 'search', 'visits'));
    }

    public function show(Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('vaccinations.view');
        $visit = $patient->visits()->vaccinationVisits()->with('service')->findOrFail($visit);
        $record = $record === null ? null : $visit->vaccinationRecords()->findOrFail($record);
        $history = VaccinationRecord::with(['visit', 'recorder'])
            ->whereHas('visit', fn ($query) => $query->where('patient_id', $patient->id))
            ->orderByDesc('administered_on')->latest('id')->paginate(15);

        return view('vaccinations.show', compact('patient', 'visit', 'record', 'history'));
    }

    public function save(Request $request, Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('vaccinations.view');
        Gate::authorize('vaccinations.record');
        $visit = $patient->visits()->vaccinationVisits()->findOrFail($visit);
        if ($record !== null) {
            $visit->vaccinationRecords()->findOrFail($record);
        }
        $data = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:200'],
            'dose' => ['required', 'string', 'max:100'],
            'administered_on' => ['required', 'date_format:Y-m-d', 'date_equals:'.$visit->visit_date->toDateString(), 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'administered_by' => ['required', 'string', 'max:200'],
            'next_appointment' => ['nullable', 'date_format:Y-m-d', 'after:administered_on'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'confirm' => ['accepted'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'submission_token' => $record === null ? ['required', 'uuid'] : ['prohibited'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $record, $data) {
            $visit = $patient->visits()->vaccinationVisits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can have vaccination records changed.']);
            }
            $entry = $record === null ? null : $visit->vaccinationRecords()->findOrFail($record);
            if ((int) $data['lock_version'] !== ($entry?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'This vaccination record changed. Reload and review the latest entry.']);
            }
            if (! $entry && VaccinationRecord::where('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['submission_token' => 'This entry was already saved. Review the vaccination history before adding another.']);
            }
            $creating = ! $entry;
            $entry ??= new VaccinationRecord;
            if ($creating) {
                $entry->visit_id = $visit->id;
                $entry->created_by = $request->user()->id;
                $entry->submission_token = $data['submission_token'];
            }
            foreach (['vaccine_name', 'dose', 'administered_on', 'batch_number', 'administered_by', 'next_appointment', 'remarks'] as $field) {
                $entry->$field = $data[$field] ?? null;
            }
            $entry->updated_by = $request->user()->id;
            $entry->lock_version = (int) $data['lock_version'] + 1;
            $entry->save();
            activity('vaccinations')->causedBy($request->user())->performedOn($entry)
                ->withProperties(['visit_id' => $visit->id, 'version' => $entry->lock_version])
                ->log($creating ? 'vaccination.created' : 'vaccination.updated');
        });

        return redirect()->route('vaccinations.show', [$patient, $visit])->with('status', 'Vaccination record saved.');
    }
}
