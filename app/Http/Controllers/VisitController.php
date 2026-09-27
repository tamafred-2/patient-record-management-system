<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Gate::allows('viewAny', Visit::class) || Gate::allows('analytics.overview'), 403);
        $today = now('Asia/Manila')->toDateString();
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim($input['search'] ?? '');
        $from = $input['from'] ?? $today;
        $to = $input['to'] ?? $from;
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered range of no more than 366 dates.']);
        }
        $visits = Visit::with(['patient', 'service'])->whereHas('patient')
            ->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to)
            ->searchPatientOrQueue($search)->orderByDesc('visit_date')->latest('id')->paginate(20)->withQueryString();

        return view('visits.index', compact('visits', 'today', 'from', 'to', 'search'));
    }

    public function monitor(Patient $patient, string $visit)
    {
        Gate::authorize('visits.monitor');
        abort_unless(auth()->user()->is_active, 403);
        $visit = $patient->visits()->with(['service', 'completedBy:id,name'])->findOrFail($visit);
        $creator = User::find($visit->created_by);

        return view('visits.monitor', compact('patient', 'visit', 'creator'));
    }

    public function create(Patient $patient)
    {
        Gate::authorize('create', [Visit::class, $patient]);

        return view('visits.create', ['patient' => $patient, 'services' => Service::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, Patient $patient)
    {
        Gate::authorize('create', [Visit::class, $patient]);
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'visit_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'queue_reference' => ['required', 'string', 'max:100'],
            'patient_id' => ['prohibited'], 'visit_number' => ['prohibited'],
            'created_by' => ['prohibited'], 'status' => ['prohibited'], 'completed_at' => ['prohibited'], 'completed_by' => ['prohibited'], 'completion_remarks' => ['prohibited'],
        ]);
        if ($patient->birth_date && $data['visit_date'] < $patient->birth_date->toDateString()) {
            throw ValidationException::withMessages(['visit_date' => 'The visit date cannot be before the patient’s birth date.']);
        }
        $visit = DB::transaction(function () use ($data, $patient, $request) {
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            if (! $service->is_active) {
                throw ValidationException::withMessages(['service_id' => 'This service is no longer active. Choose another service.']);
            }
            $visit = new Visit;
            $visit->patient()->associate($patient);
            $visit->service()->associate($service);
            $visit->visit_date = $data['visit_date'];
            $visit->queue_reference = $data['queue_reference'];
            $visit->created_by = $request->user()->id;
            $visit->status = 'OPEN';
            $visit->save();
            $visit->visit_number = 'V-'.str_replace('-', '', $data['visit_date']).'-'.str_pad((string) $visit->id, 4, '0', STR_PAD_LEFT);
            $visit->save();
            activity('visits')->causedBy($request->user())->performedOn($visit)
                ->withProperties(['patient_id' => $patient->id, 'service_id' => $service->id, 'status' => 'OPEN'])->log('visit.created');

            return $visit;
        });

        return redirect()->route('patients.visits.show', [$patient, $visit])->with('status', 'Visit created.');
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('view', $patient);
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        Gate::authorize('view', $visit);

        return view('visits.show', compact('patient', 'visit'));
    }
}
