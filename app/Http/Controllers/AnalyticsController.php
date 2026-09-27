<?php

namespace App\Http\Controllers;

use App\Models\DispositionRecord;
use App\Models\LaboratoryRecord;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\Visit;
use App\Services\DiagnosisLabels;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('analytics.view');
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'interval' => ['nullable', 'in:daily,weekly,monthly,yearly']]);
        $from = $input['from'] ?? now('Asia/Manila')->startOfMonth()->toDateString();
        $to = $input['to'] ?? now('Asia/Manila')->toDateString();
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered range of no more than 366 dates.']);
        }
        $interval = $input['interval'] ?? 'daily';
        $overview = Gate::allows('analytics.overview');
        $can = fn (string $permission) => $overview || Gate::allows($permission);
        $visits = Visit::whereHas('patient')->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to);
        $ids = (clone $visits)->select('visits.id');
        $panels = [];
        $trend = [];
        if ($can('visits.view')) {
            $total = (clone $visits)->count();
            $patients = (clone $visits)->distinct()->count('patient_id');
            $returning = (clone $visits)->whereExists(function ($q) use ($from) {
                $q->selectRaw('1')->from('visits as earlier')->whereColumn('earlier.patient_id', 'visits.patient_id')->whereDate('earlier.visit_date', '<', $from);
            })->distinct()->count('patient_id');
            $days = (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
            $previousFrom = CarbonImmutable::parse($from)->subDays($days)->toDateString();
            $previousTo = CarbonImmutable::parse($from)->subDay()->toDateString();
            $previous = Visit::whereHas('patient')->whereDate('visit_date', '>=', $previousFrom)->whereDate('visit_date', '<=', $previousTo)->count();
            $panels['Patient trends'] = ['Visits' => $total, 'Distinct patients' => $patients, 'First recorded visit in period' => $patients - $returning, 'Patients seen before period' => $returning, 'Visits in preceding equal period' => $previous, 'Change in visit count' => $total - $previous];
            $grouped = (clone $visits)->selectRaw('visit_date, COUNT(*) as total')->groupBy('visit_date')->get()->mapWithKeys(fn ($r) => [$r->visit_date->toDateString() => (int) $r->total]);
            for ($date = CarbonImmutable::parse($from); $date->toDateString() <= $to; $date = $date->addDay()) {
                $trend[$date->toDateString()] = $grouped[$date->toDateString()] ?? 0;
            }
            $groups = (clone $visits)->selectRaw('service_id, COUNT(*) as total')->groupBy('service_id')->get();
            $names = Service::withTrashed()->whereIn('id', $groups->pluck('service_id'))->pluck('name', 'id');
            $panels['Service demand'] = $groups->mapWithKeys(fn ($r) => [($names[$r->service_id] ?? 'Former service').' (#'.$r->service_id.')' => (int) $r->total])->all();
        }
        if ($can('vitals.view')) {
            $recorded = (clone $visits)->has('vitalSign')->count();
            $panels['Initial assessment'] = ['With vital signs' => $recorded, 'Without vital signs' => (clone $visits)->count() - $recorded];
        }
        if ($overview) {
            $panels['PhilHealth confirmation'] = [
                'With record' => (clone $visits)->whereHas('eligibilityCheck', fn ($q) => $q->where('philhealth_confirmed', true))->count(),
                'No record' => (clone $visits)->whereHas('eligibilityCheck', fn ($q) => $q->where('philhealth_confirmed', false))->count(),
                'Not checked / legacy' => (clone $visits)->where(fn ($q) => $q->doesntHave('eligibilityCheck')->orWhereHas('eligibilityCheck', fn ($r) => $r->whereNull('philhealth_confirmed')))->count(),
            ];
        }
        $screening = null;
        $diseases = null;
        $diagnosisCoverage = null;
        if ($can('consultations.view')) {
            $records = TreatmentRecord::whereIn('visit_id', $ids);
            $recorded = (clone $records)->count();
            $panels['Consultation records'] = ['With ITR' => $recorded, 'Without ITR' => (clone $visits)->count() - $recorded];
            $diagnosisCoverage = ['with' => 0, 'without' => 0];
            $diagnosisGroups = [];
            foreach ((clone $records)->join('visits', 'visits.id', '=', 'treatment_records.visit_id')->select('treatment_records.diagnoses', 'visits.patient_id')->cursor() as $record) {
                if (! $record->diagnoses) {
                    $diagnosisCoverage['without']++;

                    continue;
                }
                $diagnosisCoverage['with']++;
                $seen = [];
                foreach ($record->diagnoses as $label) {
                    $key = DiagnosisLabels::normalize($label);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $diagnosisGroups[$key] ??= ['label' => $key, 'visits' => 0, 'patients' => []];
                    $diagnosisGroups[$key]['visits']++;
                    $diagnosisGroups[$key]['patients'][$record->patient_id] = true;
                }
            }
            $diseases = collect($diagnosisGroups)->map(fn ($r) => ['label' => $r['label'], 'visits' => $r['visits'], 'patients' => count($r['patients'])])->sort(fn ($a, $b) => ($b['visits'] <=> $a['visits']) ?: strcmp($a['label'], $b['label']))->take(10)->values()->all();
            $screening = [];
            foreach (TreatmentRecord::QUESTIONS as $field => $label) {
                $yes = (clone $records)->where($field, true)->count();
                $no = (clone $records)->where($field, false)->count();
                $screening[] = ['label' => $label, 'yes' => $yes, 'no' => $no, 'unanswered' => $recorded - $yes - $no];
            }
            $panels['Supporting records'] = [];
            foreach (DispositionRecord::TYPES as $type => $label) {
                $panels['Supporting records'][$label] = DispositionRecord::whereIn('visit_id', $ids)->where('type', $type)->count();
            }
        }
        if ($can('laboratory.view')) {
            $panels['Laboratory activity'] = [];
            foreach (LaboratoryRecord::STATUSES as $status => $label) {
                $panels['Laboratory activity'][$label] = LaboratoryRecord::whereIn('visit_id', $ids)->where('availability_status', $status)->count();
            }
        }
        if ($can('pharmacy.view') || $can('consultations.view')) {
            $rx = Prescription::whereIn('visit_id', $ids);
            $panels['Prescription activity'] = ['Issued prescriptions' => (clone $rx)->where('status', 'ISSUED')->count()];
            if ($can('consultations.view')) {
                $panels['Prescription activity']['Draft prescriptions'] = (clone $rx)->where('status', 'DRAFT')->count();
            }
            $panels['Prescription activity']['Awaiting first release'] = (clone $rx)->where('status', 'ISSUED')->doesntHave('dispensings')->count();
            $panels['Prescription activity']['With at least one release'] = (clone $rx)->where('status', 'ISSUED')->has('dispensings')->count();
        }

        return view('analytics.index', compact('interval', 'from', 'to', 'overview', 'panels', 'trend', 'screening', 'diseases', 'diagnosisCoverage'));
    }
}
