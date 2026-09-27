<?php

namespace Tests\Feature;

use App\Models\EligibilityCheck;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Patient $patient;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('Information Staff');
        $this->actingAs($staff)->post('/patients', ['first_name' => 'Fictional', 'last_name' => 'Verification'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['queue_reference' => 'TEST-001', 'service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->officer = User::factory()->create();
        $this->officer->assignRole('Doctor / Medical Officer');
        $this->actingAs($this->officer);
    }

    private function url(?Patient $patient = null): string
    {
        return route('consultations.show', [$patient ?? $this->patient, $this->visit]);
    }

    public function test_doctor_reviews_visit_and_missing_records(): void
    {
        $this->get(route('consultations.index'))->assertOk()->assertSee('Fictional Verification');
        $this->get($this->url())->assertOk()->assertSee($this->visit->visit_number)->assertSee('Not recorded')->assertSee('Not checked');
        $this->get(route('consultations.index', ['date' => '2000-01-01']))->assertOk()->assertDontSee('Fictional Verification');
        $this->get(route('consultations.index', ['date' => 'invalid']))->assertSessionHasErrors('date');
    }

    public function test_summary_reads_measurements_and_confirmation_without_write_access(): void
    {
        $nurse = User::factory()->create();
        $nurse->assignRole('Nurse');
        $this->actingAs($nurse)->put(route('assessments.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'temperature' => 36.75])->assertSessionHasNoErrors();
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin)->put(route('eligibility.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'philhealth_confirmed' => 1, 'remarks' => '<script>fictional</script>'])->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->get($this->url())->assertOk()->assertSee('36.75 Celsius')->assertSee('With record')->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $this->get('/patients')->assertForbidden();
        $this->put(route('assessments.save', [$this->patient, $this->visit]), ['lock_version' => 1, 'temperature' => 37])->assertForbidden();
        $this->put(route('eligibility.save', [$this->patient, $this->visit]), ['lock_version' => 1, 'philhealth_confirmed' => 0])->assertForbidden();
        $this->post($this->url(), [])->assertStatus(405);
        $this->assertDatabaseHas('vital_signs', ['temperature' => 36.75, 'lock_version' => 1]);
        $this->assertDatabaseHas('eligibility_checks', ['philhealth_confirmed' => 1, 'lock_version' => 1]);
    }

    public function test_denied_roles_cross_patient_and_archived_patient(): void
    {
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-FICTIONAL-OTHER';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('consultations.index'))->assertForbidden();
            $this->get($this->url())->assertForbidden();
        }
        $this->actingAs($this->officer);
        $this->patient->delete();
        $this->get($this->url())->assertNotFound();
        $this->get(route('consultations.index'))->assertDontSee('Fictional Verification');
    }

    public function test_saved_unconfirmed_and_legacy_values_are_not_shown_as_confirmed(): void
    {
        $record = new EligibilityCheck;
        $record->visit_id = $this->visit->id;
        $record->status = 'Legacy example';
        $record->verified_by = $this->officer->id;
        $record->updated_by = $this->officer->id;
        $record->verified_at = now();
        $record->save();
        $this->get($this->url())->assertOk()->assertSee('Not checked');
        $record->philhealth_confirmed = false;
        $record->save();
        $this->get($this->url())->assertOk()->assertSee('No record');
    }
}
