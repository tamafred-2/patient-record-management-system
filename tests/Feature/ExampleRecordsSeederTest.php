<?php

namespace Tests\Feature;

use App\Models\DispositionRecord;
use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\ExampleRecordsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ExampleRecordsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_examples_cover_statuses_relationships_and_authorized_outputs(): void
    {
        $this->withoutVite();
        $this->seed(ExampleRecordsSeeder::class);
        $this->assertDatabaseCount('patients', 9);
        $this->assertDatabaseCount('visits', 10);
        $this->assertEqualsCanonicalizing(array_keys(LaboratoryRecord::STATUSES), LaboratoryRecord::distinct()->pluck('availability_status')->all());
        $this->assertEqualsCanonicalizing(array_keys(DispositionRecord::TYPES), DispositionRecord::distinct()->pluck('type')->all());
        foreach (['Draft' => 0, 'Waiting' => 0, 'Partial' => 4, 'Released' => 10] as $name => $released) {
            $rx = Prescription::where('prescription_number', 'EXAMPLE-RX-'.strtoupper($name))->firstOrFail();
            $this->assertSame($name === 'Draft' ? 'DRAFT' : 'ISSUED', $rx->status);
            $visit = Visit::findOrFail($rx->visit_id);
            $this->assertSame($visit->treatmentRecord->id, $rx->treatment_record_id);
            $this->assertCount(2, $rx->items);
            foreach ($rx->items as $item) {
                $this->assertEquals($released, DB::table('dispensing_items')->where('prescription_item_id', $item->id)->sum('quantity_dispensed'));
            }
        }
        $visit = Visit::where('visit_number', 'EXAMPLE-VISIT-RELEASED')->firstOrFail();
        $this->assertCount(2, $visit->patient->visits);
        foreach (['doctor' => 'consultations.show', 'nurse' => 'assessments.edit', 'admin' => 'eligibility.edit', 'pharmacist' => 'pharmacy.show', 'medtech' => 'laboratory.show'] as $role => $route) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get(route($route, [$visit->patient, $visit]))->assertOk();
        }
        $this->actingAs(User::where('email', 'doctor@rhu.test')->firstOrFail());
        $this->get(route('visits.pdf', [$visit->patient, $visit]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('history.show', $visit->patient))->assertOk()->assertSee('Medicine release recorded');
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail());
        $this->get('/reports')->assertOk()->assertSee('Laboratory')->assertSee('Medical Certificate');
        $this->get('/audit')->assertOk()->assertSee('Fictional workflow seeded');
    }

    public function test_reseeding_preserves_edits_archived_profiles_and_unrelated_records(): void
    {
        $this->seed(ExampleRecordsSeeder::class);
        $patient = Patient::where('patient_number', 'EXAMPLE-PATIENT-DRAFT')->firstOrFail();
        $patient->first_name = 'Edited example';
        $patient->save();
        $itr = $patient->visits->first()->treatmentRecord;
        $itr->assessment = 'Edited assessment';
        $itr->save();
        Patient::where('patient_number', 'EXAMPLE-PATIENT-PARTIAL')->firstOrFail()->delete();
        $unrelated = new Patient(['first_name' => 'Unrelated fictional', 'last_name' => 'Profile']);
        $unrelated->patient_number = 'UNRELATED-TEST';
        $unrelated->created_by = User::where('email', 'information@rhu.test')->firstOrFail()->id;
        $unrelated->duplicate_key = Patient::duplicateKey($unrelated->first_name, $unrelated->last_name);
        $unrelated->save();
        $tables = ['patients', 'visits', 'vital_signs', 'eligibility_checks', 'treatment_records', 'prescriptions', 'prescription_items', 'dispensings', 'dispensing_items', 'laboratory_records', 'disposition_records', 'activity_log'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->seed(ExampleRecordsSeeder::class);
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public function test_production_is_rejected_before_any_records_are_seeded(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->seed(ExampleRecordsSeeder::class);
            $this->fail('Production seeding should fail.');
        } catch (LogicException $exception) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('patients', 0);
            $this->assertDatabaseCount('services', 0);
        }
    }
}
