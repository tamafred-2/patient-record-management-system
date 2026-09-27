<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CustomReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail());
        Visit::query()->update(['visit_date' => '2025-01-01']);
    }

    public function test_filters_grouping_selected_columns_and_privacy(): void
    {
        $visits = Visit::take(3)->get();
        foreach ($visits as $i => $visit) {
            $visit->forceFill(['visit_date' => $i === 2 ? '2026-10-01' : '2026-09-26', 'status' => $i === 1 ? 'COMPLETED' : 'OPEN', 'completion_remarks' => 'PRIVATE-COMPLETION', 'queue_reference' => 'QUEUE-SECRET'])->save();
        }
        $params = ['from' => '2026-09-01', 'to' => '2026-10-01', 'format' => 'both', 'group' => 'month', 'configured' => 1, 'columns' => ['completed_at']];
        $response = $this->get('/reports?'.http_build_query($params))->assertOk();
        $this->assertSame(3, $response->viewData('total'));
        $this->assertSame([2, 1], $response->viewData('rows')->pluck('total')->all());
        $this->assertSame(1, $response->viewData('completed'));
        $response->assertDontSee('PRIVATE-COMPLETION')->assertDontSee('QUEUE-SECRET');
        foreach ($visits as $visit) {
            $response->assertDontSee($visit->patient->full_name)->assertDontSee($visit->patient->patient_number);
        }
        $filtered = $this->get('/reports?'.http_build_query([...$params, 'service_ids' => [$visits[1]->service_id], 'status' => 'COMPLETED']))->assertOk();
        $this->assertSame(1, $filtered->viewData('total'));
        $this->assertSame(0, $filtered->viewData('open'));
        $filtered->assertDontSee('<th>Open</th>', false)->assertDontSee('0 open')->assertSee('<th>Completed</th>', false);
        $openOnly = $this->get('/reports?'.http_build_query([...$params, 'status' => 'OPEN']))->assertOk();
        $openOnly->assertDontSee('<th>Completed</th>', false)->assertDontSee('0 completed')->assertSee('<th>Open</th>', false);
        $response->assertSee('<th>Open</th>', false)->assertSee('<th>Completed</th>', false);

        $visits[1]->patient->delete();
        $this->assertSame(2, $this->get('/reports?'.http_build_query($params))->viewData('total'));
        $empty = $this->get('/reports?from=2024-01-01&to=2024-01-01&format=both&configured=1')->assertOk()->assertSee('No visits match');
        $this->assertSame([], $empty->viewData('columns'));
    }

    public function test_presets_and_invalid_export_options(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 26));
        foreach (['today' => '2026-09-26', 'week' => '2026-09-21', 'month' => '2026-09-01'] as $period => $from) {
            $response = $this->get('/reports?period='.$period)->assertOk();
            $this->assertSame($from, $response->viewData('from'));
            $this->assertSame('custom', $response->viewData('filters')['period']);
        }
        foreach (['columns[0]=patient_name', 'columns[0]=completion_remarks', 'format=clinical', 'group=invalid', 'status=invalid', 'service_ids[0]=999999'] as $query) {
            $this->get('/reports/pdf?'.$query)->assertSessionHasErrors();
        }
    }

    public function test_oversized_register_requires_narrowing_but_summary_remains_available(): void
    {
        $source = Visit::firstOrFail();
        $records = [];
        for ($i = 0; $i < 2001; $i++) {
            $records[] = ['patient_id' => $source->patient_id, 'service_id' => $source->service_id,
                'visit_date' => '2026-09-26', 'visit_number' => 'LIMIT-'.$i, 'status' => 'OPEN', 'created_by' => auth()->id()];
        }
        foreach (array_chunk($records, 100) as $chunk) {
            Visit::insert($chunk);
        }
        $this->from('/reports')->get('/reports/pdf?from=2026-09-26&to=2026-09-26&format=register')->assertSessionHasErrors('format');
        $this->get('/reports/pdf?from=2026-09-26&to=2026-09-26&format=summary')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_multipage_pdf_exports_all_rows_and_audits_selection(): void
    {
        $visit = Visit::firstOrFail();
        $visit->forceFill(['visit_date' => '2026-09-26', 'status' => 'COMPLETED', 'completed_by' => auth()->id(), 'completed_at' => '2026-09-26 01:00:00', 'completion_remarks' => 'PRIVATE-COMPLETION'])->save();
        for ($i = 0; $i < 65; $i++) {
            $copy = $visit->replicate();
            $copy->visit_number = 'QA-REPORT-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $copy->save();
        }
        $params = ['from' => '2026-09-26', 'to' => '2026-09-26', 'format' => 'both', 'group' => 'service'];
        $preview = $this->get('/reports?'.http_build_query($params))->assertOk();
        $this->assertSame(25, $preview->viewData('register')->count());
        $this->assertSame(66, $preview->viewData('register')->total());
        $pdf = $this->get('/reports/pdf?'.http_build_query($params))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $dir = storage_path('framework/testing/report-qa');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/custom-report.pdf', $pdf->getContent());
        $summary = $this->get('/reports/pdf?'.http_build_query([...$params, 'format' => 'summary']))->assertOk();
        file_put_contents($dir.'/summary-report.pdf', $summary->getContent());
        $completedPdf = $this->get('/reports/pdf?'.http_build_query([...$params, 'format' => 'summary', 'status' => 'COMPLETED']))->assertOk();
        file_put_contents($dir.'/completed-report.pdf', $completedPdf->getContent());

        $event = Activity::where('description', 'report.exported')->latest('id')->firstOrFail();
        $this->assertSame('summary', $event->properties['format']);
        $this->assertArrayNotHasKey('patient_id', $event->properties->all());
    }
}
