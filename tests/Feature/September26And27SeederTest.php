<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use Database\Seeders\September26And27Seeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class September26And27SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dates_services_and_repeat_runs_preserve_edits_and_archived_patients(): void
    {
        $this->seed(September26And27Seeder::class);
        $this->assertSame(60, Visit::count());
        foreach (['2026-09-26', '2026-09-27'] as $date) {
            $visits = Visit::whereDate('visit_date', $date)->get();
            $this->assertCount(30, $visits);
            $this->assertCount(6, $visits->groupBy('service_id'));
            $this->assertCount(30, $visits->pluck('queue_reference')->unique());
            foreach ($visits as $visit) {
                $this->assertSame('OPEN', $visit->status);
                $this->assertNull($visit->completed_at);
                $this->assertSame($visit->created_at->toDateTimeString(), $visit->patient->created_at->toDateTimeString());
            }
        }
        $patient = Patient::firstOrFail();
        $patient->update(['first_name' => 'Edited']);
        $visit = $patient->visits()->firstOrFail();
        $visit->forceFill(['queue_reference' => 'MANUAL-123'])->save();
        $patient->delete();
        $this->seed(September26And27Seeder::class);
        $this->assertSame(60, Visit::count());
        $this->assertSame(60, Patient::withTrashed()->count());
        $this->assertSame('Edited', $patient->fresh()->first_name);
        $this->assertTrue($patient->fresh()->trashed());
        $this->assertSame('MANUAL-123', $visit->fresh()->queue_reference);
    }

    public function test_disabled_services_are_preserved_and_skipped(): void
    {
        $this->seed(ServiceSeeder::class);
        $service = Service::where('seed_key', 'VACCINATION')->firstOrFail();
        $service->update(['is_active' => false]);
        $this->seed(September26And27Seeder::class);
        $this->assertSame(50, Visit::count());
        $this->assertFalse($service->fresh()->is_active);
        $this->assertSame(0, Visit::where('service_id', $service->id)->count());
    }

    public function test_production_is_rejected(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\LogicException::class);
        $this->seed(September26And27Seeder::class);
    }
}
