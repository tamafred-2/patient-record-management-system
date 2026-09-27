<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\HistoricalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_records_have_linked_history_and_preserve_edits_on_repeat(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00:00', 'Asia/Manila'));
        $this->seed(HistoricalDemoSeeder::class);
        $this->assertDatabaseCount('patients', 48);
        $count = Visit::count();
        $this->assertGreaterThan(200, $count);
        $this->assertSame($count, TreatmentRecord::count());
        $this->assertSame('2026-01-05', substr(Visit::min('visit_date'), 0, 10));
        $this->assertSame('2026-09-28', substr(Visit::max('visit_date'), 0, 10));
        $this->assertGreaterThan(0, TreatmentRecord::where('fever', true)->count());
        $this->assertGreaterThan(0, TreatmentRecord::where('fever', false)->count());
        $this->assertGreaterThan(0, TreatmentRecord::whereNull('family_hypertension')->count());
        $this->assertSame(0, TreatmentRecord::whereNull('cough_colds')->count());
        $january = Visit::whereDate('visit_date', '<', '2026-02-02')->get()->groupBy(fn ($v) => $v->visit_date->toDateString())->map->count();
        $this->assertGreaterThan(1, $january->max());
        $this->assertGreaterThan(1, $january->unique()->count());
        foreach (Patient::all() as $patient) {
            $this->assertGreaterThanOrEqual(2, $patient->visits->count());
            $this->assertNull($patient->contact_number);
            foreach ($patient->visits as $visit) {
                $this->assertNotNull($visit->treatmentRecord);
                $this->assertNotEmpty($visit->treatmentRecord->objective_snapshot);
                $this->assertTrue($patient->created_at->lte($visit->created_at));
            }
        }
        $patient = Patient::firstOrFail();
        $patient->update(['first_name' => 'Edited example']);
        $patient->delete();
        $this->assertSame(0, Visit::whereDate('visit_date', '<', '2026-09-25')->where('status', 'OPEN')->count());
        $this->assertGreaterThan(0, Visit::where('status', 'COMPLETED')->whereNotNull('completed_at')->count());
        $this->assertSame(0, Visit::whereDate('visit_date', '>=', '2026-09-25')->whereNotNull('completed_at')->count());
        $beforeVisits = Visit::all()->toJson();
        $before = TreatmentRecord::all()->toJson();
        $this->seed(HistoricalDemoSeeder::class);
        $this->assertSame(48, Patient::withTrashed()->count());
        $this->assertSame($count, Visit::count());
        $this->assertSame($before, TreatmentRecord::all()->toJson());
        $this->assertSame($beforeVisits, Visit::all()->toJson());
        $this->assertSame('Edited example', $patient->fresh()->first_name);
        $this->withoutVite();
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail())
            ->get('/analytics?from=2026-01-05&to=2026-09-28')
            ->assertOk()->assertSee('Most recorded diagnoses chart')->assertSee('hypertension');
    }

    public function test_production_is_rejected_before_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->seed(HistoricalDemoSeeder::class);
            $this->fail('Production must reject demo records.');
        } catch (\LogicException $exception) {
            $this->assertDatabaseCount('patients', 0);
            $this->assertDatabaseCount('users', 0);
        }
    }
}
