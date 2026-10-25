<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryRecord;
use App\Models\MidwifeCareRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\VaccinationRecord;
use App\Models\Visit;
use App\Support\FollowUpReminders;
use App\Support\ListFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('dashboard.view');
        $input = $request->validate(['search' => ListFilters::search(), 'service_id' => ['nullable', 'integer', 'min:1']]);
        $search = trim($input['search'] ?? '');
        $serviceId = $input['service_id'] ?? null;
        $today = now('Asia/Manila')->toDateString();
        $todayVisitQuery = fn () => Visit::whereHas('patient')->whereDate('visit_date', $today);
        $todayVisitIds = (clone $todayVisitQuery())->select('visits.id');
        $summaryCards = [];
        $overview = Gate::allows('analytics.overview');

        if ($overview || Gate::allows('viewAny', Visit::class)) {
            $total = (clone $todayVisitQuery())->count();
            $summaryCards = [
                ['Visits today', $total, 'All registered visits for today', '#today-visits'],
                ['Open today', (clone $todayVisitQuery())->where('status', Visit::OPEN)->count(), "Today's visits not yet completed"],
                ['Completed today', (clone $todayVisitQuery())->where('status', Visit::COMPLETED)->count(), "Today's visits marked completed"],
                ['All visits', Visit::whereHas('patient')->count(), 'All dates · View visit history', route('visits.index', ['all' => 1])],
            ];
        }

        if (Gate::allows('viewAny', Patient::class) && Gate::allows('viewAny', Visit::class)) {
            $counts = (clone $todayVisitQuery())->selectRaw('service_id, COUNT(*) as total')->groupBy('service_id')->pluck('total', 'service_id');
            $services = Service::withTrashed()->where(function ($query) use ($counts) {
                $query->where(fn ($active) => $active->where('is_active', true)->whereNull('deleted_at'))
                    ->orWhereIn('id', $counts->keys());
            })->orderByRaw("CASE WHEN seed_key = 'OTHER' THEN 1 ELSE 0 END")->orderBy('name')->get();
            foreach ($services as $service) {
                $summaryCards[] = [$service->name.' today', (int) ($counts[$service->id] ?? 0), 'Visits requesting this service today', route('dashboard', ['service_id' => $service->id]).'#today-visits'];
            }
        }

        if (! $overview && Gate::allows('vitals.view')) {
            $saved = (clone $todayVisitQuery())->has('vitalSign')->count();
            if (! collect($summaryCards)->contains(fn ($card) => $card[0] === 'Visits today')) {
                $summaryCards[] = [Gate::allows('consultations.view') ? 'Assessment visits today' : 'Visits today', (clone $todayVisitQuery())->count(), 'All registered visits for today', '#today-assessments'];
            }
            $monthStart = now('Asia/Manila')->startOfMonth()->toDateString();
            $monthlyAssessments = Visit::whereHas('patient')->whereDate('visit_date', '>=', $monthStart)->whereDate('visit_date', '<=', $today)->has('vitalSign')->count();
            $summaryCards[] = ['Assessed this month', $monthlyAssessments, "This month's assessments", Gate::allows('analytics.view') ? route('analytics.index', ['from' => $monthStart, 'to' => $today]) : '#today-assessments'];
            $summaryCards[] = ['Assessed today', $saved, 'Visits with saved initial assessment', '#today-assessments'];
            $summaryCards[] = ['Not yet assessed', (clone $todayVisitQuery())->count() - $saved, 'No assessment recorded', '#today-assessments'];
            $summaryCards[] = ['Reassessment requests', Visit::whereHas('patient')->where('status', Visit::OPEN)->has('pendingAssessmentRequest')->count(), 'Pending requests across all dates', route('assessments.index', ['reassessment' => 1])];
        }
        if (! $overview && Gate::allows('consultations.view')) {
            $allVisits = Visit::whereHas('patient')->has('vitalSign');
            $saved = (clone $allVisits)->has('treatmentRecord')->count();
            if (! collect($summaryCards)->contains(fn ($card) => $card[0] === 'Visits today')) {
                $summaryCards[] = ['Visits today', (clone $todayVisitQuery())->has('vitalSign')->count(), "Today's visits with recorded vital signs", '#today-visits'];
            }
            $summaryCards[] = ['ITRs saved', $saved, 'All dates', route('consultations.index', ['all' => 1, 'record' => 'with_itr'])];
            $summaryCards[] = ['Without saved ITR', (clone $allVisits)->count() - $saved, 'All dates · No ITR recorded', route('consultations.index', ['all' => 1, 'record' => 'without_itr'])];
            $summaryCards[] = ['Issued prescriptions', (clone $allVisits)->whereHas('prescription', fn ($query) => $query->issued())->count(), 'All dates', route('consultations.index', ['all' => 1, 'record' => 'issued'])];
        }
        if (! $overview && Gate::allows('laboratory.view')) {
            $summaryCards[] = ['Total lab records', LaboratoryRecord::whereIn('visit_id', Visit::whereHas('patient')->select('id'))->count(), 'All dates', route('laboratory.index', ['all' => 1])];
            foreach (['PERFORMED_RHU' => 'Laboratory services at RHU', 'EXTERNAL_ADVISED' => 'External laboratory advice', 'PENDING' => 'Pending laboratory records'] as $status => $label) {
                $summaryCards[] = [$label, LaboratoryRecord::whereIn('visit_id', $todayVisitIds)->where('availability_status', $status)->count(), 'Recorded for today\'s visits'];
            }
        }
        if (! $overview && Gate::allows('pharmacy.view')) {
            $summaryCards[] = ['Total prescriptions', Prescription::whereIn('visit_id', Visit::whereHas('patient')->select('id'))->issued()->count(), 'All issued prescriptions', route('pharmacy.index', ['all' => 1])];
            $start = now('Asia/Manila')->startOfDay();
            $releases = DB::table('dispensings')->whereNull('dispensings.voided_at')
                ->whereExists(fn ($items) => $items->selectRaw('1')->from('dispensing_items')->whereColumn('dispensing_items.dispensing_id', 'dispensings.id')->where('dispensing_items.quantity_dispensed', '>', 0))->join('prescriptions', 'prescriptions.id', '=', 'dispensings.prescription_id')->join('visits', 'visits.id', '=', 'prescriptions.visit_id')->join('patients', 'patients.id', '=', 'visits.patient_id')->whereNull('patients.deleted_at')
                ->where('dispensings.dispensed_at', '>=', $start->copy()->utc())->where('dispensings.dispensed_at', '<', $start->copy()->addDay()->utc())->count();
            $summaryCards[] = ['Releases today', $releases, "Today's medicine releases", route('pharmacy.index', ['all' => 1])];
            $prescriptions = Prescription::whereIn('visit_id', $todayVisitIds)->issued();
            $summaryCards[] = ['Issued prescriptions', (clone $prescriptions)->count(), 'Available for Pharmacy'];
            $summaryCards[] = ['Awaiting first release', (clone $prescriptions)->doesntHave('activeDispensings')->count(), 'Issued prescriptions without a release'];
        }
        if (! $overview && Gate::allows('midwife-care.view')) {
            $summaryCards[] = ['Midwife care records', MidwifeCareRecord::whereIn('visit_id', $todayVisitIds)->count(), 'Saved for today\'s visits'];
            $summaryCards[] = ['Care follow-ups recorded', MidwifeCareRecord::whereIn('visit_id', $todayVisitIds)->whereNotNull('follow_up_on')->count(), 'Records with a follow-up date'];
        }
        if (! $overview && Gate::allows('vaccinations.view')) {
            $summaryCards[] = ['Vaccinations recorded', VaccinationRecord::whereIn('visit_id', $todayVisitIds)->count(), 'Saved for today\'s visits'];
            $summaryCards[] = ['Next appointments recorded', VaccinationRecord::whereIn('visit_id', $todayVisitIds)->whereNotNull('next_appointment')->count(), 'Vaccinations with a next appointment'];
        }

        $todayVisits = (Gate::allows('viewAny', Visit::class) || Gate::allows('analytics.overview') || Gate::allows('consultations.view'))
            ? $this->visitQuery($search, $serviceId)->cursorPaginate(10)->withQueryString() : null;
        $todayVisitsTotal = $todayVisits !== null ? $this->visitQuery($search, $serviceId)->count() : 0;
        $nextVisitsUrl = $todayVisits?->hasMorePages() ? route('dashboard.visits', array_filter(['search' => $search, 'service_id' => $serviceId, 'cursor' => $todayVisits->nextCursor()->encode()])) : null;

        $departmentBatches = [];
        $departmentTotals = [];
        $departmentNext = [];
        foreach (['assessments', 'laboratory', 'pharmacy', 'midwife', 'vaccinations'] as $section) {
            if (! $this->allowsSection($section)) {
                continue;
            }
            $query = $this->sectionQuery($section, $search);
            $departmentTotals[$section] = (clone $query)->count();
            $batch = $query->cursorPaginate(10, ['*'], $section.'_cursor');
            $departmentBatches[$section] = $batch;
            $departmentNext[$section] = $batch->hasMorePages() ? route('dashboard.visits', array_filter(['section' => $section, 'search' => $search, 'cursor' => $batch->nextCursor()->encode()])) : null;
        }
        $todayPharmacy = $departmentBatches['pharmacy'] ?? null;
        $todayVaccinations = $departmentBatches['vaccinations'] ?? null;
        $todayMidwifeCare = $departmentBatches['midwife'] ?? null;
        $todayAssessments = $departmentBatches['assessments'] ?? null;
        $todayLaboratory = $departmentBatches['laboratory'] ?? null;

        foreach ($summaryCards as &$card) {
            if (isset($card[3])) {
                continue;
            }
            $card[3] = match ($card[0]) {
                'Initial assessments saved', 'Without saved assessment' => '#today-assessments',
                'ITRs saved', 'Without saved ITR' => '#today-visits',
                'Issued prescriptions' => Gate::allows('pharmacy.view') ? '#today-pharmacy' : '#today-visits',
                'Awaiting first release' => '#today-pharmacy',
                'Midwife care records', 'Care follow-ups recorded' => '#today-midwife',
                'Vaccinations recorded', 'Next appointments recorded' => '#today-vaccinations',
                default => '#today-laboratory',
            };
        }
        unset($card);

        FollowUpReminders::dispatchDue();
        $followUps = Gate::allows('viewAny', Visit::class) || Gate::allows('consultations.view')
            ? FollowUpReminders::outstanding()->with('followUpSetter')->whereDate('follow_up_on', '<=', now('Asia/Manila')->addDays(7)->toDateString())->orderBy('follow_up_on')->limit(30)->get() : collect();

        return view('dashboard', compact('followUps', 'summaryCards', 'today', 'todayVisits', 'todayVisitsTotal', 'nextVisitsUrl', 'serviceId', 'todayPharmacy', 'todayVaccinations', 'todayMidwifeCare', 'search', 'todayAssessments', 'todayLaboratory', 'departmentBatches', 'departmentTotals', 'departmentNext'));
    }

    private function visitQuery(string $search, ?int $serviceId, bool $doctorList = true)
    {
        return Visit::with(['patient', 'service' => fn ($query) => $query->withTrashed()])->whereHas('patient')->whereDate('visit_date', now('Asia/Manila')->toDateString())
            ->when($doctorList && Gate::allows('consultations.view') && ! Gate::allows('viewAny', Visit::class) && ! Gate::allows('analytics.overview'), fn ($query) => $query->has('vitalSign'))
            ->when($serviceId, fn ($query) => $query->where('service_id', $serviceId))
            ->searchPatientOrQueue($search)->priorityFirst();
    }

    private function allowsSection(string $section): bool
    {
        return match ($section) {
            'visits' => Gate::allows('viewAny', Visit::class) || Gate::allows('analytics.overview') || Gate::allows('consultations.view'),
            'assessments' => Gate::allows('vitals.view'),
            'laboratory' => Gate::allows('laboratory.view'),
            'pharmacy' => Gate::allows('pharmacy.view'),
            'midwife' => Gate::allows('midwife-care.view'),
            'vaccinations' => Gate::allows('vaccinations.view'),
            default => false,
        };
    }

    private function sectionQuery(string $section, string $search)
    {
        $query = $this->visitQuery($search, null, false);

        return match ($section) {
            'assessments' => $query->with('pendingAssessmentRequest'),
            'laboratory' => $query->forLaboratory(),
            'pharmacy' => $query->with('prescription')->whereHas('prescription', fn ($prescription) => $prescription->issued()),
            'midwife' => $query->midwifeCareVisits()->withCount('midwifeCareRecords'),
            'vaccinations' => $query->vaccinationVisits()->withCount('vaccinationRecords'),
            default => $query,
        };
    }

    public function visits(Request $request)
    {
        Gate::authorize('dashboard.view');
        $input = $request->validate(['search' => ListFilters::search(), 'service_id' => ['nullable', 'integer', 'min:1'], 'cursor' => ['nullable', 'string', 'max:1000'], 'section' => ['nullable', 'in:visits,assessments,laboratory,pharmacy,midwife,vaccinations']]);
        $section = $input['section'] ?? 'visits';
        abort_unless($this->allowsSection($section), 403);
        $search = trim($input['search'] ?? '');
        $query = $section === 'visits' ? $this->visitQuery($search, $input['service_id'] ?? null) : $this->sectionQuery($section, $search);
        $visits = $query->cursorPaginate(10)->withQueryString();

        return response()->json([
            'html' => view($section === 'visits' ? 'visits.rows' : 'dashboard.department-rows', ['sectionType' => $section, 'visits' => $visits, 'dashboardActions' => Gate::allows('consultations.view') || Gate::allows('eligibility.view')])->render(),
            'next' => $visits->nextPageUrl(),
            'previous' => $visits->previousPageUrl(),
            'count' => $visits->count(),
        ]);
    }
}
