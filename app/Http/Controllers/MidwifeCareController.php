<?php

namespace App\Http\Controllers;

use App\Models\MidwifeCareRecord;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MidwifeCareController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('midwife-care.view');
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'search' => ['nullable', 'string', 'max:100']]);
        $date = $data['date'] ?? now('Asia/Manila')->toDateString();
        $search = trim($data['search'] ?? '');
        $visits = Visit::midwifeCareVisits()->with('patient')->withCount('midwifeCareRecords')
            ->whereDate('visit_date', $date)->searchPatientOrQueue($search)->latest('id')->paginate(20)->withQueryString();

        return view('midwife-care.index', compact('date', 'search', 'visits'));
    }

    public function show(Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('midwife-care.view');
        $visit = $patient->visits()->midwifeCareVisits()->with('service')->findOrFail($visit);
        $record = $record === null ? null : $visit->midwifeCareRecords()->findOrFail($record);
        $history = MidwifeCareRecord::with(['visit', 'recorder', 'editor'])
            ->whereHas('visit', fn ($query) => $query->where('patient_id', $patient->id))
            ->latest('id')->paginate(15);

        $vaccinationVisit = Gate::allows('vaccinations.view') ? $patient->visits()->vaccinationVisits()->orderByDesc('visit_date')->latest('id')->first() : null;

        return view('midwife-care.show', compact('patient', 'visit', 'record', 'history', 'vaccinationVisit'));
    }

    public function save(Request $request, Patient $patient, string $visit, ?string $record = null)
    {
        Gate::authorize('midwife-care.view');
        Gate::authorize('midwife-care.record');
        $visit = $patient->visits()->midwifeCareVisits()->findOrFail($visit);
        if ($record !== null) {
            $visit->midwifeCareRecords()->findOrFail($record);
        }
        $data = $request->validate([
            'care_type' => ['required', Rule::in(array_keys(MidwifeCareRecord::TYPES))],
            'purpose' => ['required', 'string', 'max:3000'],
            'services_provided' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'location' => ['nullable', 'required_if:care_type,COMMUNITY', 'prohibited_unless:care_type,COMMUNITY', 'string', 'max:200'],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d', 'after:'.$visit->visit_date->toDateString()],
            'confirm' => ['accepted'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'submission_token' => $record === null ? ['required', 'uuid'] : ['prohibited'],
            'patient_id' => ['prohibited'], 'visit_id' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ]);
        DB::transaction(function () use ($request, $patient, $visit, $record, $data) {
            $visit = $patient->visits()->midwifeCareVisits()->whereKey($visit->id)->lockForUpdate()->firstOrFail();
            if ($visit->status !== 'OPEN') {
                throw ValidationException::withMessages(['visit' => 'Only open visits can have Midwife care records changed.']);
            }
            $entry = $record === null ? null : $visit->midwifeCareRecords()->findOrFail($record);
            if ((int) $data['lock_version'] !== ($entry?->lock_version ?? 0)) {
                throw ValidationException::withMessages(['lock_version' => 'This care record changed. Reload and review the latest entry.']);
            }
            if (! $entry && MidwifeCareRecord::where('submission_token', $data['submission_token'])->exists()) {
                throw ValidationException::withMessages(['submission_token' => 'This entry was already saved. Review the care history before adding another.']);
            }
            $creating = ! $entry;
            $entry ??= new MidwifeCareRecord;
            if ($creating) {
                $entry->visit_id = $visit->id;
                $entry->created_by = $request->user()->id;
                $entry->submission_token = $data['submission_token'];
            }
            foreach (['care_type', 'purpose', 'services_provided', 'notes', 'location', 'follow_up_on'] as $field) {
                $entry->$field = $data[$field] ?? null;
            }
            $entry->updated_by = $request->user()->id;
            $entry->lock_version = (int) $data['lock_version'] + 1;
            $entry->save();
            activity('midwife-care')->causedBy($request->user())->performedOn($entry)
                ->withProperties(['visit_id' => $visit->id, 'version' => $entry->lock_version])
                ->log($creating ? 'midwife-care.created' : 'midwife-care.updated');
        });

        return redirect()->route('midwife-care.show', [$patient, $visit])->with('status', 'Midwife care record saved.');
    }
}
