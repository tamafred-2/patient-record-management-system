<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Models\Visit;
use Database\Seeders\RealisticDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealisticDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_records_have_linked_history_and_preserve_edits_on_repeat(): void
    {
        $this->seed(RealisticDemoSeeder::class);
        $this->assertDatabaseCount('patients', 8);
        $this->assertDatabaseCount('visits', 16);
        $this->assertDatabaseCount('treatment_records', 16);
        foreach (Patient::all() as $patient) {
            $this->assertCount(2, $patient->visits);
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
        $before = TreatmentRecord::all()->toJson();
        $this->seed(RealisticDemoSeeder::class);
        $this->assertSame(8, Patient::withTrashed()->count());
        $this->assertSame(16, Visit::count());
        $this->assertSame($before, TreatmentRecord::all()->toJson());
        $this->assertSame('Edited example', $patient->fresh()->first_name);
    }

    public function test_production_is_rejected_before_writes(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->seed(RealisticDemoSeeder::class);
            $this->fail('Production must reject demo records.');
        } catch (\LogicException $exception) {
            $this->assertDatabaseCount('patients', 0);
            $this->assertDatabaseCount('users', 0);
        }
    }
}
