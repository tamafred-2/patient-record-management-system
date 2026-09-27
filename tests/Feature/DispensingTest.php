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

class DispensingTest extends TestCase
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
        $this->officer->assignRole('Doctor / Medical Officer');
        $this->actingAs($this->officer);
        $this->put(route('itr.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'vitals_version' => 0, 'assessment' => 'Fictional assessment'])->assertSessionHasNoErrors();
    }

    private function pharmacyUrl(?Patient $patient = null): string
    {
        return route('pharmacy.show', [$patient ?? $this->patient, $this->visit]);
    }

    private function preparePrescription(bool $issue = true): void
    {
        $url = route('prescriptions.show', [$this->patient, $this->visit]);
        $this->put($url, ['lock_version' => 0, 'items' => [
            ['medicine_name' => 'Fictional medicine A', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => '5.50'],
            ['medicine_name' => 'Fictional medicine B', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => '2.00'],
        ]])->assertSessionHasNoErrors();
        if ($issue) {
            $this->post(route('prescriptions.issue', [$this->patient, $this->visit]), ['lock_version' => 1, 'confirm' => 1])->assertSessionHasNoErrors();
        }
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('Pharmacist');
        $this->actingAs($pharmacist);
    }

    private function releaseData(int $version = 2): array
    {
        $items = Prescription::firstOrFail()->items;

        return ['lock_version' => $version, 'confirm' => 1, 'quantities' => [$items[0]->id => '1.25', $items[1]->id => '0'], 'remarks' => '<script>fictional</script>'];
    }

    public function test_partial_then_full_release_preserves_prescription_and_audit_privacy(): void
    {
        $this->preparePrescription();
        $rx = Prescription::firstOrFail();
        $original = $rx->items->toArray();
        $this->get('/pharmacy')->assertOk()->assertSee($rx->prescription_number);
        $this->get($this->pharmacyUrl())->assertOk()->assertSee('Not dispensed')->assertDontSee('Fictional assessment');
        $this->post($this->pharmacyUrl(), $this->releaseData())->assertSessionHasNoErrors();
        $this->get($this->pharmacyUrl())->assertSee('Partially dispensed')->assertSee('4.25')->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $items = $rx->items;
        $data = $this->releaseData(3);
        $data['quantities'] = [$items[0]->id => '4.25', $items[1]->id => '2'];
        $this->post($this->pharmacyUrl(), $data)->assertSessionHasNoErrors();
        $this->get($this->pharmacyUrl())->assertSee('Fully dispensed');
        $this->assertDatabaseCount('dispensings', 2);
        $this->assertDatabaseCount('dispensing_items', 3);
        $this->assertSame($original, $rx->fresh()->items->toArray());
        $this->assertSame('ISSUED', $rx->fresh()->status);
        $this->assertSame('OPEN', $this->visit->fresh()->status);
        $audit = Activity::where('description', 'dispensing.recorded')->firstOrFail();
        $this->assertSame(['visit_id', 'prescription_id', 'version'], array_keys($audit->properties->all()));
    }

    public function test_over_release_duplicates_stale_and_invalid_requests(): void
    {
        $this->preparePrescription();
        $base = $this->releaseData();
        $id = array_key_first($base['quantities']);
        foreach (['5.51', '-1', '1.001', '1e1', 'not numeric'] as $value) {
            $data = $base;
            $data['quantities'] = [$id => $value];
            $this->post($this->pharmacyUrl(), $data)->assertSessionHasErrors();
        }
        foreach ([['confirm' => 0], ['quantities' => [$id => 0]], ['quantities' => [999999 => 1]], ['pharmacist_id' => 999], ['prescription_id' => 999]] as $extra) {
            $this->post($this->pharmacyUrl(), array_replace($base, $extra))->assertSessionHasErrors();
        }
        $this->post($this->pharmacyUrl(), $base)->assertSessionHasNoErrors();
        $this->post($this->pharmacyUrl(), $base)->assertSessionHasErrors('lock_version');
        $this->assertDatabaseCount('dispensings', 1);
    }

    public function test_role_ownership_and_clinical_access_boundaries(): void
    {
        $this->preparePrescription();
        $data = $this->releaseData();
        $this->get('/patients')->assertForbidden();
        $this->get(route('itr.edit', [$this->patient, $this->visit]))->assertForbidden();
        $this->get(route('prescriptions.show', [$this->patient, $this->visit]))->assertForbidden();
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-PHARMACY-OTHER';
        $other->save();
        $this->get($this->pharmacyUrl($other))->assertNotFound();
        $this->post($this->pharmacyUrl($other), $data)->assertNotFound();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'Doctor / Medical Officer', 'MedTech', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->get('/pharmacy')->assertForbidden();
            $this->get($this->pharmacyUrl())->assertForbidden();
            $this->post($this->pharmacyUrl(), $data)->assertForbidden();
        }
        $reader = User::factory()->create();
        $reader->givePermissionTo('pharmacy.view');
        $this->actingAs($reader);
        $this->get($this->pharmacyUrl())->assertOk();
        $this->post($this->pharmacyUrl(), $data)->assertForbidden();
        $this->assertDatabaseCount('dispensings', 0);
    }

    public function test_draft_closed_and_archived_visit_and_date_filter(): void
    {
        $this->preparePrescription(false);
        $this->get('/pharmacy')->assertDontSee('RX-000001');
        $this->get($this->pharmacyUrl())->assertNotFound();
        $this->post($this->pharmacyUrl(), $this->releaseData(1))->assertNotFound();
        $rx = Prescription::firstOrFail();
        $rx->status = 'ISSUED';
        $rx->prescribed_at = now();
        $rx->save();
        $this->get('/pharmacy?date=2000-01-01')->assertDontSee($rx->prescription_number);
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->pharmacyUrl(), $this->releaseData(1))->assertSessionHasErrors('visit');
        $this->patient->delete();
        $this->get($this->pharmacyUrl())->assertNotFound();
        $this->get('/pharmacy')->assertDontSee($rx->prescription_number);
        $this->assertDatabaseCount('dispensings', 0);
    }
}
