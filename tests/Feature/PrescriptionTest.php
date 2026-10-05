<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PrescriptionTest extends TestCase
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
        $this->actingAs($staff)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Verification'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString(), 'queue_reference' => 'TEST-001'])->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->officer = User::factory()->create();
        $this->officer->assignRole('Doctor / Medical Officer');
        $this->actingAs($this->officer);
        $this->put(route('itr.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'vitals_version' => 0, 'assessment' => 'Fictional assessment'])->assertSessionHasNoErrors();
    }

    private function url(?Patient $patient = null): string
    {
        return route('prescriptions.show', [$patient ?? $this->patient, $this->visit]);
    }

    private function data(int $version = 0): array
    {
        return ['lock_version' => $version, 'items' => [['medicine_name' => 'Fictional medicine', 'dosage' => 'Example dose', 'frequency' => 'Example frequency', 'quantity_prescribed' => 5]]];
    }

    public function test_quantities_are_whole_numbers_and_reuse_is_scoped_to_the_doctor_and_active_patients(): void
    {
        foreach (['1.5', '0', '-1', '100000000'] as $quantity) {
            $data = $this->data();
            $data['items'][0]['quantity_prescribed'] = $quantity;
            $this->put($this->url(), $data)->assertSessionHasErrors('items.0.quantity_prescribed');
        }
        $this->assertDatabaseCount('prescriptions', 0);
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->get($this->url())->assertOk()->assertViewHas('suggestions', fn ($values) => $values['dosage']->contains('Example dose'))
            ->assertDontSee('Reuse medicine name')->assertSee('Show strength (optional) options')->assertSee('Show dosage options')->assertSee('step="1"', false)->assertSee('Other - type your own value');
        $other = User::factory()->create();
        $other->assignRole('Doctor / Medical Officer');
        $this->actingAs($other)->get($this->url())->assertOk()
            ->assertViewHas('suggestions', fn ($values) => ! $values['dosage']->contains('Example dose'));
        $this->actingAs($this->officer);
        $this->patient->delete();
        // Archived patients are excluded from the history query even when another active visit is viewed.
        $active = $this->patient->replicate();
        $active->patient_number = 'ACTIVE-RX-TEST';
        $active->deleted_at = null;
        $active->save();
        $visit = $this->visit->replicate();
        $visit->patient_id = $active->id;
        $visit->visit_number = 'ACTIVE-RX-VISIT';
        $visit->save();
        $this->get(route('prescriptions.show', [$active, $visit]))->assertOk()
            ->assertViewHas('suggestions', fn ($values) => ! $values['dosage']->contains('Example dose'));
    }

    public function test_doctor_saves_reviews_updates_and_issues_immutable_prescription(): void
    {
        $this->get($this->url())->assertOk()->assertSee('New prescription draft')
            ->assertViewHas('suggestions', fn ($values) => $values['strength']->contains('100 mg')
                && $values['dosage']->contains('1 tablet') && $values['frequency']->contains('3 times a day')
                && $values['duration']->contains('1 week') && $values['duration']->contains('1 month'));
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = Prescription::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals($this->officer->id, $record->doctor_id);
        $this->assertEquals($this->visit->treatmentRecord->id, $record->treatment_record_id);
        $this->get($this->url())->assertSee('Fictional medicine')->assertSee('DRAFT');
        $this->put($this->url(), $this->data())->assertSessionHasErrors('lock_version');
        $this->put($this->url(), $this->data(1))->assertSessionHasNoErrors();
        $issue = route('prescriptions.issue', [$this->patient, $this->visit]);
        $this->post($issue, ['lock_version' => 2])->assertSessionHasErrors('confirm');
        $this->post($issue, ['lock_version' => 1, 'confirm' => 1])->assertSessionHasErrors('lock_version');
        $this->post($issue, ['lock_version' => 2, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertEquals('ISSUED', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->prescribed_at);
        $this->put($this->url(), $this->data(3))->assertSessionHasErrors('prescription');
        $this->post($issue, ['lock_version' => 3, 'confirm' => 1])->assertSessionHasErrors('prescription');
        $this->assertDatabaseCount('prescriptions', 1);
        $this->assertDatabaseCount('prescription_items', 1);
        $audit = Activity::where('description', 'prescription.issued')->firstOrFail();
        $this->assertSame(['visit_id', 'version', 'status'], array_keys($audit->properties->all()));
    }

    public function test_denied_roles_other_author_and_cross_patient(): void
    {
        $this->put($this->url(), $this->data())->assertSessionHasNoErrors();
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-OTHER-RX';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        $this->put($this->url($other), $this->data(1))->assertNotFound();
        $this->post(route('prescriptions.issue', [$other, $this->visit]), ['lock_version' => 1, 'confirm' => 1])->assertNotFound();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'MedTech', 'Pharmacist', 'Midwife', 'Doctor / Medical Officer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->get($this->url())->assertStatus($role === 'Doctor / Medical Officer' ? 200 : 403);
            $this->put($this->url(), $this->data(1))->assertForbidden();
            $this->post(route('prescriptions.issue', [$this->patient, $this->visit]), ['lock_version' => 1, 'confirm' => 1])->assertForbidden();
        }
    }

    public function test_validation_no_itr_and_closed_visit(): void
    {
        foreach ([
            ['items' => []], ['doctor_id' => 999], ['visit_id' => 999], ['treatment_record_id' => 999], ['status' => 'ISSUED'],
            ['items' => [['medicine_name' => 'Example', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => 0]]],
        ] as $extra) {
            $this->put($this->url(), array_replace($this->data(), $extra))->assertSessionHasErrors();
        }
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->put($this->url(), $this->data())->assertSessionHasErrors('prescription');
        $this->visit->status = 'OPEN';
        $this->visit->save();
        $this->visit->treatmentRecord()->delete();
        $this->put($this->url(), $this->data())->assertSessionHasErrors('prescription');
        $this->assertDatabaseCount('prescriptions', 0);
    }
}
