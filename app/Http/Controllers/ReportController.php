<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Visit;
use App\Services\ExportSafety;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public const MAX_REGISTER_ROWS = 2000;

    public const COLUMNS = ['queue_reference' => 'Queue reference', 'completed_at' => 'Completed at', 'completed_by' => 'Completed by'];

    public function index(Request $request)
    {
        Gate::authorize('reports.view');

        return view('reports.index', $this->data($request));
    }

    public function pdf(Request $request, ExportSafety $safety)
    {
        Gate::authorize('reports.view');
        Gate::authorize('reports.export');

        return $safety->run($request->user()->id, function () use ($request, $safety) {
            $data = $this->data($request, true);
            $html = view('reports.pdf', $data)->render();
            $safety->checkHtml($html);
            $pdf = Pdf::loadHTML($html)->setOption('isRemoteEnabled', false)
                ->setPaper('a4', $data['format'] === 'summary' ? 'portrait' : 'landscape');
            $pdf->render();
            $canvas = $pdf->getDomPDF()->getCanvas();
            $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $canvas->page_text(32, $canvas->get_height() - 24, 'RHU Calasiao | Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.35, 0.35, 0.35]);
            $response = $pdf->download('rhu-service-report-'.$data['from'].'-to-'.$data['to'].'.pdf')->header('Cache-Control', 'no-store, private');
            activity('exports')->causedBy($request->user())->withProperties($data['filters'])->log('report.exported');

            return $response;
        });
    }

    private function data(Request $request, bool $export = false): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()], 'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'period' => ['nullable', Rule::in(['custom', 'today', 'week', 'month'])],
            'service_ids' => ['nullable', 'array', 'max:100'], 'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')],
            'status' => ['nullable', Rule::in(['all', 'OPEN', 'COMPLETED'])],
            'format' => ['nullable', Rule::in(['summary', 'register', 'both'])],
            'group' => ['nullable', Rule::in(['service', 'day', 'month'])],
            'configured' => ['nullable', 'boolean'],
            'columns' => ['nullable', 'array', 'max:3'], 'columns.*' => ['string', 'distinct', Rule::in(array_keys(self::COLUMNS))],
        ]);
        $today = CarbonImmutable::now('Asia/Manila');
        $period = $validated['period'] ?? 'custom';
        $from = $validated['from'] ?? $today->startOfMonth()->toDateString();
        $to = $validated['to'] ?? $today->toDateString();
        if ($period !== 'custom') {
            $from = match ($period) {
                'today' => $today->toDateString(),
                'week' => $today->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
                'month' => $today->startOfMonth()->toDateString(),
            };
            $to = $today->toDateString();
        }
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 365) {
            throw ValidationException::withMessages(['to' => 'Choose an ordered range of no more than 366 dates.']);
        }
        $status = $validated['status'] ?? 'all';
        $format = $validated['format'] ?? 'summary';
        $group = $validated['group'] ?? 'service';
        $serviceIds = $validated['service_ids'] ?? [];
        $columns = $validated['columns'] ?? ($request->boolean('configured') ? [] : array_keys(self::COLUMNS));
        $columnLabels = self::COLUMNS;
        $services = Service::withTrashed()->orderBy('name')->get(['id', 'name', 'deleted_at']);
        $names = $services->pluck('name', 'id');
        $serviceLabel = $serviceIds ? $services->whereIn('id', $serviceIds)->pluck('name')->join(', ') : 'All services';
        $query = Visit::whereHas('patient')->whereDate('visit_date', '>=', $from)->whereDate('visit_date', '<=', $to);
        if ($serviceIds) {
            $query->whereIn('service_id', $serviceIds);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $batchOnly = ! $export && $request->header('X-Scroll-Queue') === 'register';
        $total = $batchOnly ? 0 : (clone $query)->count();
        $patients = $batchOnly ? 0 : (clone $query)->distinct()->count('patient_id');
        $open = $batchOnly ? 0 : (clone $query)->where('status', 'OPEN')->count();
        $completed = $batchOnly ? 0 : (clone $query)->where('status', 'COMPLETED')->count();
        $field = $group === 'service' ? 'service_id' : 'visit_date';
        $groups = $batchOnly ? collect() : (clone $query)->select($field)->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_count")->groupBy($field)->get();
        $rows = $groups->groupBy(function ($row) use ($group) {
            return $group === 'service' ? (string) $row->service_id : $row->visit_date->format($group === 'month' ? 'Y-m' : 'Y-m-d');
        })->map(function ($items, $key) use ($group, $names, $total) {
            $count = $items->sum('total');

            return ['service' => $group === 'service' ? ($names[$key] ?? 'Former service') : $key,
                'total' => $count, 'open' => $items->sum('open_count'), 'completed' => $items->sum('completed_count'),
                'percentage' => $total ? round($count * 100 / $total, 1) : 0];
        })->sortBy('service')->values();
        $filters = ['from' => $from, 'to' => $to, 'period' => 'custom', 'service_ids' => $serviceIds, 'status' => $status,
            'format' => $format, 'group' => $group, 'configured' => 1, 'columns' => $columns];
        $register = null;
        if ($format !== 'summary') {
            if ($export && $total > self::MAX_REGISTER_ROWS) {
                throw ValidationException::withMessages(['format' => 'The visit register PDF is limited to 2,000 visits. Narrow the dates, service or status, or choose Summary only.']);
            }
            $registerQuery = (clone $query)->select(['id', 'visit_number', 'visit_date', 'service_id', 'status', 'queue_reference', 'completed_at', 'completed_by'])
                ->with('completedBy:id,name')->orderBy('visit_date')->orderBy('id');
            $register = $export ? $registerQuery->limit(self::MAX_REGISTER_ROWS + 1)->get() : $registerQuery->scrollPaginate()->appends($filters);
            if ($export && $register->count() > self::MAX_REGISTER_ROWS) {
                throw ValidationException::withMessages(['format' => 'The register now exceeds 2,000 visits. Refresh the preview and narrow the dates, service or status, or choose Summary only.']);
            }
        }
        $generatedAt = $today->format('M j, Y g:i:s A');
        $generatedBy = $request->user()->name;
        $groupLabel = ['service' => 'Requested service', 'day' => 'Visit day', 'month' => 'Visit month'][$group];
        $formatLabel = ['summary' => 'Summary only', 'register' => 'Visit register only', 'both' => 'Summary and visit register'][$format];

        return compact('from', 'to', 'period', 'services', 'serviceIds', 'serviceLabel', 'status', 'format', 'formatLabel', 'group', 'groupLabel', 'columns', 'columnLabels', 'total', 'patients', 'open', 'completed', 'rows', 'register', 'names', 'filters', 'generatedAt', 'generatedBy', 'export');
    }
}
