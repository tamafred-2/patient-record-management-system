<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Patient::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['q'] ?? '');
        $query = Patient::query();
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            // Treat SQL LIKE metacharacters as literal input on both databases.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function ($query) use ($pattern) {
                foreach (['patient_number', 'first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                    $query->orWhereRaw("LOWER({$field}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return view('patients.index', ['patients' => $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->paginate(20)->withQueryString(), 'search' => $search]);
    }

    public function create()
    {
        Gate::authorize('create', Patient::class);

        return view('patients.form', ['patient' => new Patient, 'duplicates' => collect()]);
    }

    public function store(PatientRequest $request)
    {
        $data = Arr::only($request->validated(), Patient::DEMOGRAPHICS);
        $key = Patient::duplicateKey($data['first_name'], $data['last_name']);
        $matches = Patient::where('duplicate_key', $key)->limit(10)->get();
        if ($matches->isNotEmpty() && ! $request->boolean('confirm_distinct')) {
            return response()->view('patients.form', ['patient' => new Patient($data), 'duplicates' => $matches], 422);
        }

        $patient = DB::transaction(function () use ($data, $key, $request, $matches) {
            $patient = new Patient($data);
            $patient->created_by = $request->user()->id;
            $patient->duplicate_key = $key;
            $patient->save();
            $patient->patient_number = 'RHU-'.now()->format('Y').'-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT);
            $patient->save();
            activity('patients')->causedBy($request->user())->performedOn($patient)
                ->withProperties(['fields' => array_keys($data), 'duplicate_review_confirmed' => $matches->isNotEmpty()])->log('patient.created');

            return $patient;
        });

        return redirect()->route('patients.show', $patient)->with('status', 'Patient registered.');
    }

    public function show(Patient $patient)
    {
        Gate::authorize('view', $patient);

        $visits = Gate::allows('viewAny', Visit::class)
            ? $patient->visits()->with('service')->orderByDesc('visit_date')->orderByDesc('id')->paginate(10)
            : null;

        return view('patients.show', compact('patient', 'visits'));
    }

    public function edit(Patient $patient)
    {
        Gate::authorize('update', $patient);

        return view('patients.form', ['patient' => $patient, 'duplicates' => collect()]);
    }

    public function update(PatientRequest $request, Patient $patient)
    {
        $data = Arr::only($request->validated(), Patient::DEMOGRAPHICS);
        $key = Patient::duplicateKey($data['first_name'], $data['last_name']);
        $matches = Patient::where('duplicate_key', $key)->where('id', '!=', $patient->id)->limit(10)->get();
        if ($matches->isNotEmpty() && ! $request->boolean('confirm_distinct')) {
            $patient->fill($data);
            $patient->lock_version = $request->integer('lock_version');

            return response()->view('patients.form', ['patient' => $patient, 'duplicates' => $matches], 422);
        }

        DB::transaction(function () use ($request, $patient, $data, $key, $matches) {
            $patient->fill($data);
            $fields = array_keys(Arr::only($patient->getDirty(), Patient::DEMOGRAPHICS));
            $updated = Patient::whereKey($patient->id)->where('lock_version', $request->integer('lock_version'))
                ->update([...$data, 'duplicate_key' => $key, 'lock_version' => $request->integer('lock_version') + 1]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['lock_version' => 'This profile changed after you opened it. Copy your intended changes, reload the profile, and review before saving again.']);
            }
            activity('patients')->causedBy($request->user())->performedOn($patient)
                ->withProperties(['fields' => $fields, 'duplicate_review_confirmed' => $matches->isNotEmpty()])->log('patient.updated');
        });

        return redirect()->route('patients.show', $patient)->with('status', 'Patient profile updated.');
    }
}
