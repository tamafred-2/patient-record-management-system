<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $nurse;

    private Patient $patient;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('Information Staff');
        $this->actingAs($staff)->post('/patients', ['first_name' => 'Fictional', 'last_name' => 'Assessment'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->nurse = User::factory()->create();
        $this->nurse->assignRole('Nurse');
        $this->actingAs($this->nurse);
    }

    private function url(?Patient $patient = null): string
    {
        return route('assessments.save', [$patient ?? $this->patient, $this->visit]);
    }

    public function test_nurse_can_record_review_and_update_vitals_with_private_audits(): void
    {
        $this->get(route('assessments.index'))->assertOk()->assertSee('Fictional Assessment')->assertSee('Pending');
        $this->get($this->url())->assertOk()->assertSee('Systolic BP');
    }

    public function test_measurements_save_update_and_preserve_ownership(): void
    {
        $this->put($this->url(), ['lock_version' => 0, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'temperature' => 36.5])->assertSessionHasNoErrors()->assertRedirect();
        $record = VitalSign::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals($this->nurse->id, $record->recorded_by);
        $this->get($this->url())->assertOk()->assertSee('36.50');
        $this->get(route('assessments.index'))->assertSee('Recorded');
        $this->put($this->url(), ['lock_version' => 1, 'pulse_rate' => 70])->assertSessionHasNoErrors();
        $this->assertEquals(2, $record->fresh()->lock_version);
        $audit = Activity::where('description', 'vitals.updated')->firstOrFail();
        $this->assertArrayNotHasKey('pulse_rate', $audit->properties->all());
        $this->assertDatabaseCount('vital_signs', 1);
        $this->put($this->url(), ['lock_version' => 1, 'pulse_rate' => 90])->assertSessionHasErrors('lock_version');
        $this->assertEquals(70, $record->fresh()->pulse_rate);
    }

    public function test_invalid_measurements_and_server_fields_are_rejected(): void
    {
        foreach ([
            ['lock_version' => 0],
            ['lock_version' => 0, 'systolic_bp' => 120],
            ['lock_version' => 0, 'pulse_rate' => -1],
            ['lock_version' => 0, 'pulse_rate' => 1.5],
            ['lock_version' => 0, 'temperature' => 1000],
            ['lock_version' => 0, 'weight_kg' => 65.123],
            ['lock_version' => 0, 'pulse_rate' => 70, 'visit_id' => 999],
            ['lock_version' => 0, 'pulse_rate' => 70, 'recorded_by' => 999],
        ] as $data) {
            $this->put($this->url(), $data)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_cross_patient_closed_visit_and_denied_roles(): void
    {
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-FICTIONAL-OTHER';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        $this->put($this->url($other), ['lock_version' => 0, 'pulse_rate' => 70])->assertNotFound();
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->put($this->url(), ['lock_version' => 0, 'pulse_rate' => 70])->assertSessionHasErrors('visit');
        foreach (['System Admin', 'Information Staff', 'Doctor / Medical Officer', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('assessments.index'))->assertForbidden();
            $this->get($this->url())->assertForbidden();
            $this->put($this->url(), ['lock_version' => 0, 'pulse_rate' => 70])->assertForbidden();
        }
        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_date_filter_and_nurse_registry_boundary(): void
    {
        $this->get(route('assessments.index', ['date' => '2000-01-01']))->assertOk()->assertDontSee('Fictional Assessment');
        $this->get('/patients')->assertForbidden();
        $this->get('/services')->assertForbidden();
    }
}
