<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LaboratoryTest extends TestCase
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
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->officer = User::factory()->create();
        $this->officer->assignRole('MedTech');
        $this->actingAs($this->officer);
    }

    private function url(?Patient $patient = null): string
    {
        return route('laboratory.show', [$patient ?? $this->patient, $this->visit]);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['test_name' => 'Fictional test', 'availability_status' => 'PENDING', 'lock_version' => 0, 'submission_token' => (string) Str::uuid()], $extra);
    }

    public function test_medtech_records_edits_multiple_services_and_audits_without_results(): void
    {
        $this->get('/laboratory')->assertOk()->assertSee('Fictional Verification');
        $data = $this->data();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals($this->officer->id, $record->created_by);
        $this->post($this->url(), $data)->assertSessionHasErrors('submission_token');
        $edit = route('laboratory.edit', [$this->patient, $this->visit, $record]);
        $this->get($edit)->assertOk();
        $this->put($edit, ['test_name' => 'Fictional test', 'availability_status' => 'EXTERNAL_ADVISED', 'external_advice' => '<script>example advice</script>', 'lock_version' => 1])->assertSessionHasNoErrors();
        $this->get($this->url())->assertSee('&lt;script&gt;example advice&lt;/script&gt;', false)->assertDontSee('<script>example advice</script>', false);
        $this->put($edit, ['test_name' => 'Fictional test', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1])->assertSessionHasErrors('lock_version');
        $this->put($edit, ['test_name' => 'Fictional test', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 2])->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->external_advice);
        $this->post($this->url(), $this->data(['test_name' => 'Other fictional test']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laboratory_records', 2);
        $audit = Activity::where('description', 'laboratory.updated')->firstOrFail();
        $this->assertSame(['visit_id', 'version'], array_keys($audit->properties->all()));
        $this->assertSame('OPEN', $this->visit->fresh()->status);
    }

    public function test_validation_and_foreign_record_are_rejected(): void
    {
        foreach ([['test_name' => ''], ['availability_status' => 'UNKNOWN'], ['availability_status' => 'EXTERNAL_ADVISED'], ['external_advice' => 'Wrong status'], ['patient_id' => 999], ['visit_id' => 999], ['result' => 'Unsupported result']] as $extra) {
            $this->post($this->url(), $this->data($extra))->assertSessionHasErrors();
        }
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-LAB-OTHER';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        $this->post($this->url($other), $this->data())->assertNotFound();
        $second = $this->visit->replicate();
        $second->visit_number = 'V-LAB-OTHER';
        $second->save();
        $wrong = route('laboratory.edit', [$this->patient, $second, $record]);
        $this->get($wrong)->assertNotFound();
        $this->put($wrong, ['test_name' => 'Wrong', 'availability_status' => 'PENDING', 'lock_version' => 1])->assertNotFound();
    }

    public function test_denied_roles_read_only_and_clinical_boundary(): void
    {
        $this->get('/patients')->assertForbidden();
        $this->get(route('itr.edit', [$this->patient, $this->visit]))->assertForbidden();
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'Doctor / Medical Officer', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->get('/laboratory')->assertForbidden();
            $this->get($this->url())->assertForbidden();
            $this->post($this->url(), $this->data())->assertForbidden();
            $this->put(route('laboratory.update', [$this->patient, $this->visit, $record]), ['lock_version' => 1])->assertForbidden();
        }
        $reader = User::factory()->create();
        $reader->givePermissionTo('laboratory.view');
        $this->actingAs($reader);
        $this->get($this->url())->assertOk();
        $this->post($this->url(), $this->data())->assertForbidden();
    }

    public function test_date_closed_and_archived_visits(): void
    {
        $this->get('/laboratory?date=2000-01-01')->assertOk()->assertDontSee('Fictional Verification');
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->url(), $this->data())->assertSessionHasErrors('visit');
        $this->patient->delete();
        $this->get($this->url())->assertNotFound();
        $this->get('/laboratory')->assertDontSee('Fictional Verification');
    }
}
