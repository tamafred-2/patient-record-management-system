<?php

namespace App\Http\Controllers;

use App\Models\Dispensing;
use App\Models\DispositionRecord;
use App\Models\EligibilityCheck;
use App\Models\LaboratoryRecord;
use App\Models\MidwifeCareRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Visit;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    public const ACTIONS = [
        'staff.password_reset' => 'User password reset',
        'staff.password_changed' => 'User password changed',
        'disposition.recorded' => 'Referral or certificate request recorded',
        'report.exported' => 'Aggregate report exported',
        'visit.summary_exported' => 'Internal visit summary exported',
        'demo.workflow_seeded' => 'Fictional workflow seeded',
        'staff.created' => 'User account created', 'staff.access_updated' => 'User access updated',
        'staff.demo_seeded' => 'Demo account created', 'staff.admin_bootstrapped' => 'Administrator created',
        'staff.role_consolidated' => 'User role consolidated', 'staff.demo_retired' => 'Demo account retired',
        'patient.created' => 'Patient registered', 'patient.updated' => 'Patient demographics updated',
        'midwife-care.created' => 'Midwife care recorded', 'midwife-care.updated' => 'Midwife care updated',
        'vaccination.created' => 'Vaccination recorded', 'vaccination.updated' => 'Vaccination updated',
        'visit.completed' => 'Visit completed', 'visit.created' => 'Visit created', 'service.created' => 'Service created', 'service.updated' => 'Service updated', 'service.deleted' => 'Service removed',
        'vitals.created' => 'Vital signs recorded', 'vitals.updated' => 'Vital signs updated',
        'eligibility.created' => 'Confirmation recorded', 'eligibility.updated' => 'Confirmation updated',
        'itr.created' => 'ITR created', 'itr.updated' => 'ITR updated',
        'prescription.created' => 'Prescription draft created', 'prescription.updated' => 'Prescription draft updated', 'prescription.issued' => 'Prescription issued',
        'dispensing.recorded' => 'Medicine release recorded',
        'laboratory.created' => 'Laboratory service recorded', 'laboratory.updated' => 'Laboratory service updated',
    ];

    private const SUBJECTS = [
        DispositionRecord::class => 'Supporting record',
        User::class => 'User account', Patient::class => 'Patient',
        Visit::class => 'Visit', Service::class => 'Service',
        MidwifeCareRecord::class => 'Midwife care',
        VaccinationRecord::class => 'Vaccination',
        VitalSign::class => 'Vital signs', EligibilityCheck::class => 'Confirmation',
        TreatmentRecord::class => 'ITR', Prescription::class => 'Prescription',
        Dispensing::class => 'Medicine release', LaboratoryRecord::class => 'Laboratory service',
    ];

    public function index(Request $request)
    {
        Gate::authorize('audit.view');
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'staff' => ['nullable', 'regex:/^(system|[1-9][0-9]*)$/'],
            'action' => ['nullable', Rule::in(array_keys(self::ACTIONS))],
        ]);
        if (! $request->has('from') && ! $request->has('to')) {
            $filters['from'] = now('Asia/Manila')->subDays(6)->toDateString();
            $filters['to'] = now('Asia/Manila')->toDateString();
        }
        if (! empty($filters['from']) && ! empty($filters['to']) && $filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => 'End date must be on or after the start date.']);
        }
        $query = Activity::query()->select(['id', 'description', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'created_at']);
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], 'Asia/Manila')->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'Asia/Manila')->addDay()->utc());
        }
        $userType = (new User)->getMorphClass();
        if (! empty($filters['staff'])) {
            if ($filters['staff'] === 'system') {
                $query->whereNull('causer_id');
            } else {
                $query->where('causer_type', $userType)->where('causer_id', $filters['staff']);
            }
        }
        if (! empty($filters['action'])) {
            $query->where('description', $filters['action']);
        }
        $events = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $staff = User::orderBy('name')->get(['id', 'name', 'is_active']);
        $staffById = $staff->keyBy('id');
        $events->getCollection()->transform(function ($event) use ($staffById, $userType) {
            $event->action_label = self::ACTIONS[$event->description] ?? 'Other recorded activity';
            $event->record_label = (self::SUBJECTS[$event->subject_type] ?? 'Record').($event->subject_id !== null ? ' #'.$event->subject_id : '');
            $event->actor_label = $event->causer_id === null ? 'System / unattributed' : ($event->causer_type === $userType ? ($staffById->get($event->causer_id)?->name ?? 'Former user #'.$event->causer_id) : 'Other actor');

            return $event;
        });
        $actions = self::ACTIONS;

        return view('audit.index', compact('events', 'staff', 'filters', 'actions'));
    }
}
