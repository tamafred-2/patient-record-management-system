<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Services\QueueNumbers;
use App\Support\ListFilters;
use App\Support\RegistrationAlerts;
use App\Support\WorkflowAlerts;
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
            'search' => ListFilters::search(),
            'all' => ['sometimes', 'boolean'],
            'from' => ListFilters::pastDate(),
            'to' => ListFilters::pastDate(),
        ]);
        $search = trim($input['search'] ?? '');
        $allDates = (bool) ($input['all'] ?? false);
        $from = $input['from'] ?? $today;
        $to = $input['to'] ?? $from;
        if (! $allDates && ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365)) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered range of no more than 366 dates.']);
        }
        $visits = Visit::with(['patient', 'service'])->whereHas('patient')
            ->when(! $allDates, fn ($query) => $query->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to))
            ->searchPatientOrQueue($search)->orderByDesc('visit_date')->priorityFirst()->scrollPaginate()->withQueryString();

        return view('visits.index', compact('visits', 'today', 'from', 'to', 'search', 'allDates'));
    }

    public function monitor(Patient $patient, string $visit)
    {
        Gate::authorize('visits.monitor');
        abort_unless(auth()->user()->is_active, 403);
        $visit = $patient->visits()->with(['service', 'completedBy:id,name'])->findOrFail($visit);
        $creator = User::find($visit->created_by);

        return view('visits.monitor', compact('patient', 'visit', 'creator'));
    }

    public function create(Request $request, Patient $patient, QueueNumbers $numbers)
    {
        Gate::authorize('create', [Visit::class, $patient]);

        $key = 'pending_queue.'.$patient->id;
        $reservation = DB::table('queue_reservations')->where('token', $request->session()->get($key))
            ->where('patient_id', $patient->id)->where('user_id', $request->user()->id)->where('queue_date', now('Asia/Manila')->toDateString())->whereNull('visit_id')->first();
        if (! $reservation) {
            $reservation = $numbers->reserve($patient, $request->user());
            $request->session()->put($key, $reservation->token);
        }

        return view('visits.create', ['patient' => $patient, 'reservation' => $reservation, 'services' => Service::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, Patient $patient)
    {
        Gate::authorize('create', [Visit::class, $patient]);
        $date = $request->validate(['visit_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()]])['visit_date'];
        $senior = $patient->isSeniorOn($date);
        $request->mergeIfMissing(['priority' => $senior ? 'PRIORITY' : 'REGULAR']);
        if ($senior && $request->input('priority') === 'PRIORITY' && ! $request->filled('priority_reason')) {
            $request->merge(['priority_reason' => 'SENIOR']);
        }
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'visit_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'queue_token' => ['nullable', 'uuid'],
            'patient_id' => ['prohibited'], 'visit_number' => ['prohibited'],
            'created_by' => ['prohibited'], 'status' => ['prohibited'], 'completed_at' => ['prohibited'], 'completed_by' => ['prohibited'], 'completion_remarks' => ['prohibited'],
            'priority_rank' => ['prohibited'],
        ] + Visit::priorityRules($senior));
        if ($data['priority'] !== 'REGULAR') {
            Gate::authorize('visits.prioritize');
        }
        if ($patient->birth_date && $data['visit_date'] < $patient->birth_date->toDateString()) {
            throw ValidationException::withMessages(['visit_date' => 'The visit date cannot be before the patient’s birth date.']);
        }
        $visit = DB::transaction(function () use ($data, $patient, $request) {
            $token = $data['queue_token'] ?? $request->session()->get('pending_queue.'.$patient->id);
            if (! $token) {
                $token = app(QueueNumbers::class)->reserve($patient, $request->user())->token;
            }
            $reservation = DB::table('queue_reservations')->where('token', $token)
                ->where('patient_id', $patient->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if (! $reservation || ! $reservation->queue_reference) {
                throw ValidationException::withMessages(['queue_token' => 'Reload Start visit to generate your queue number.']);
            }
            if ($reservation->visit_id) {
                return $patient->visits()->findOrFail($reservation->visit_id);
            }
            if ($reservation->queue_date !== now('Asia/Manila')->toDateString()) {
                throw ValidationException::withMessages(['queue_token' => 'This queue number has expired. Reload Start visit for today’s number.']);
            }
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            if (! $service->is_active) {
                throw ValidationException::withMessages(['service_id' => 'This service is no longer active. Choose another service.']);
            }
            $visit = new Visit;
            $visit->patient()->associate($patient);
            $visit->service()->associate($service);
            $visit->visit_date = $data['visit_date'];
            $visit->queue_reference = $reservation->queue_reference;
            $visit->created_by = $request->user()->id;
            $visit->status = Visit::OPEN;
            $visit->applyPriority($data);
            $visit->save();
            $visit->visit_number = 'V-'.str_replace('-', '', $data['visit_date']).'-'.str_pad((string) $visit->id, 4, '0', STR_PAD_LEFT);
            $visit->save();
            activity('visits')->causedBy($request->user())->performedOn($visit)
                ->withProperties(['patient_id' => $patient->id, 'service_id' => $service->id, 'status' => 'OPEN', 'priority' => $visit->priority, 'priority_reason' => $visit->priority_reason])->log('visit.created');
            DB::table('queue_reservations')->where('id', $reservation->id)->update(['visit_id' => $visit->id]);
            RegistrationAlerts::send($patient, $request->user(), $visit);
            if ($visit->priority === 'EMERGENCY') {
                WorkflowAlerts::emergency($visit, $request->user());
            } else {
                WorkflowAlerts::send('visit_waiting', $visit, WorkflowAlerts::nurses()->merge(WorkflowAlerts::midwivesFor($visit)), $request->user());
            }

            return $visit;
        }, 5);

        $request->session()->forget('pending_queue.'.$patient->id);

        return redirect()->route('patients.visits.show', [$patient, $visit])->with('status', 'Visit created.');
    }

    public function show(Patient $patient, string $visit)
    {
        Gate::authorize('view', $patient);
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        Gate::authorize('view', $visit);

        return view('visits.show', compact('patient', 'visit'));
    }

    public function stub(Patient $patient, string $visit)
    {
        Gate::authorize('view', $patient);
        $visit = $patient->visits()->with('service')->findOrFail($visit);
        Gate::authorize('view', $visit);

        return response()->view('visits.stub', compact('patient', 'visit'))
            ->header('Cache-Control', 'private, no-store');
    }
}
