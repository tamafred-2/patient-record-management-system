<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\RegistrationAlerts;
use App\Support\TableSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public const AGE_GROUPS = ['child' => 'Child (0–17)', 'adult' => 'Adult (18–59)', 'senior' => 'Senior citizen (60+)'];

    public const VISIT_FILTERS = ['open' => 'Has an open visit', 'none' => 'No visits yet'];

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Patient::class);
        $barangays = Patient::whereNotNull('barangay')->where('barangay', '!=', '')->distinct()->orderBy('barangay')->pluck('barangay');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'sex' => ['nullable', Rule::in(Patient::SEX_OPTIONS)],
            'age' => ['nullable', Rule::in(array_keys(self::AGE_GROUPS))],
            'barangay' => ['nullable', 'string', 'max:150'],
            'visits' => ['nullable', Rule::in(array_keys(self::VISIT_FILTERS))],
        ]);
        $search = trim($data['q'] ?? '');
        $query = Patient::query();
        TableSearch::apply($query, $search, Patient::SEARCH_COLUMNS);
        $query->when($data['sex'] ?? null, fn ($query, $sex) => $query->where('sex', $sex))
            ->when($data['barangay'] ?? null, fn ($query, $barangay) => $query->where('barangay', $barangay))
            ->when($data['age'] ?? null, function ($query, $age) {
                // Age is compared through birth-date cutoffs so the filter works on SQLite and PostgreSQL alike.
                $today = now('Asia/Manila')->startOfDay();
                $adultFrom = $today->copy()->subYears(18)->toDateString();
                $seniorFrom = $today->copy()->subYears(60)->toDateString();
                match ($age) {
                    'child' => $query->whereDate('birth_date', '>', $adultFrom),
                    'adult' => $query->whereDate('birth_date', '<=', $adultFrom)->whereDate('birth_date', '>', $seniorFrom),
                    'senior' => $query->whereDate('birth_date', '<=', $seniorFrom),
                };
            })
            ->when(($data['visits'] ?? null) === 'open', fn ($query) => $query->whereHas('visits', fn ($visits) => $visits->where('status', Visit::OPEN)))
            ->when(($data['visits'] ?? null) === 'none', fn ($query) => $query->doesntHave('visits'));

        return view('patients.index', [
            'patients' => $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->scrollPaginate()->withQueryString(),
            'search' => $search,
            'filterOptions' => [
                'sex' => ['label' => 'Sex', 'options' => array_combine(Patient::SEX_OPTIONS, Patient::SEX_OPTIONS)],
                'age' => ['label' => 'Age group', 'options' => self::AGE_GROUPS],
                'barangay' => ['label' => 'Barangay', 'options' => $barangays->combine($barangays)->all()],
                'visits' => ['label' => 'Visits', 'options' => self::VISIT_FILTERS],
            ],
        ]);
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
        $exactMatch = Patient::where('duplicate_key', $key)->lazyById()
            ->first(fn (Patient $candidate) => $candidate->hasSameDemographics($data));
        if ($exactMatch) {
            return response()->view('patients.form', ['patient' => new Patient($data), 'duplicates' => collect([$exactMatch]), 'exactDuplicate' => true], 422);
        }
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
            RegistrationAlerts::send($patient, $request->user());

            return $patient;
        });

        return redirect()->route('patients.show', $patient)->with('status', 'Patient registered.');
    }

    public function show(Request $request, Patient $patient)
    {
        Gate::authorize('view', $patient);
        $filters = $request->validate(Visit::historyFilterRules());

        $visits = Gate::allows('viewAny', Visit::class)
            ? $patient->visits()->with('service')->filterHistory($filters)->orderByDesc('visit_date')->orderByDesc('id')->scrollPaginate()
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
        $proposed = array_replace($patient->only(Patient::DEMOGRAPHICS), $data);
        $exactMatch = Patient::where('duplicate_key', $key)->where('id', '!=', $patient->id)->lazyById()
            ->first(fn (Patient $candidate) => $candidate->hasSameDemographics($proposed));
        if ($exactMatch) {
            $patient->fill($data);
            $patient->lock_version = $request->integer('lock_version');

            return response()->view('patients.form', ['patient' => $patient, 'duplicates' => collect([$exactMatch]), 'exactDuplicate' => true], 422);
        }
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
