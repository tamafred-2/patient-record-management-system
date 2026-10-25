<?php

namespace App\Http\Controllers;

use App\Models\MidwifeCareRecord;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Monthly counts of midwife care and vaccinations. Aggregates only: no patient names or care notes.
class MidwifeReportController extends Controller
{
    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->can('midwife-care.view') || $user->can('vaccinations.view') || $user->can('reports.view'), 403);
    }

    private function data(Request $request): array
    {
        $this->authorizeView($request);
        $current = now('Asia/Manila')->format('Y-m');
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.$current]])['month'] ?? $current;
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01');
        // whereDate comparisons, not whereBetween: SQLite stores these dates with a time part.
        [$from, $to] = [$start->toDateString(), $start->endOfMonth()->toDateString()];

        $care = DB::table('midwife_care_records')->join('visits', 'visits.id', '=', 'midwife_care_records.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')->whereNull('patients.deleted_at')
            ->whereDate('visits.visit_date', '>=', $from)->whereDate('visits.visit_date', '<=', $to)
            ->groupBy('midwife_care_records.care_type')
            ->select('midwife_care_records.care_type', DB::raw('count(*) as entries'), DB::raw('count(distinct visits.patient_id) as patients'))
            ->get()->keyBy('care_type');
        $careRows = collect(MidwifeCareRecord::TYPES)->map(fn ($label, $type) => ['label' => $label, 'entries' => (int) ($care[$type]->entries ?? 0), 'patients' => (int) ($care[$type]->patients ?? 0)]);
        $prenatalWithLmp = DB::table('midwife_care_records')->join('visits', 'visits.id', '=', 'midwife_care_records.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')->whereNull('patients.deleted_at')
            ->whereDate('visits.visit_date', '>=', $from)->whereDate('visits.visit_date', '<=', $to)->where('care_type', 'PRENATAL')->whereNotNull('lmp')->count();
        $vaccines = DB::table('vaccination_records')->join('visits', 'visits.id', '=', 'vaccination_records.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')->whereNull('patients.deleted_at')
            ->whereDate('vaccination_records.administered_on', '>=', $from)->whereDate('vaccination_records.administered_on', '<=', $to)
            ->groupBy('vaccination_records.vaccine_name', 'vaccination_records.dose')
            ->select('vaccination_records.vaccine_name', 'vaccination_records.dose', DB::raw('count(*) as doses'))
            ->orderBy('vaccination_records.vaccine_name')->orderBy('vaccination_records.dose')->get();
        $visits = [
            'Midwife Care visits' => Visit::midwifeCareVisits()->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to)->count(),
            'Vaccination visits' => Visit::vaccinationVisits()->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to)->count(),
        ];

        return compact('month', 'start', 'careRows', 'prenatalWithLmp', 'vaccines', 'visits', 'current');
    }

    public function index(Request $request)
    {
        return view('midwife-report.index', $this->data($request));
    }

    public function print(Request $request)
    {
        $data = $this->data($request);
        activity('exports')->causedBy($request->user())->withProperties(['month' => $data['month']])->log('midwife.report_printed');

        return response()->view('midwife-report.print', $data)->header('Cache-Control', 'private, no-store');
    }
}
