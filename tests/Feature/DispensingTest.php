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
        $this->actingAs($staff)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Verification'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString(), 'queue_reference' => 'TEST-PHARMACY'])->assertSessionHasNoErrors()->assertRedirect();
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
            ['medicine_name' => 'Fictional medicine A', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => '6'],
            ['medicine_name' => 'Fictional medicine B', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => '2'],
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
        $this->get($this->pharmacyUrl())->assertSee('Partially dispensed')->assertSee('4.75')->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $items = $rx->items;
        $data = $this->releaseData(3);
        $data['quantities'] = [$items[0]->id => '4.75', $items[1]->id => '2'];
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
        foreach (['6.01', '-1', '1.001', '1e1', 'not numeric'] as $value) {
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

    public function test_substitute_buy_outside_and_declined_outcomes_close_remaining_quantities(): void
    {
        $this->preparePrescription();
        $rx = Prescription::firstOrFail();
        [$a, $b] = [$rx->items[0]->id, $rx->items[1]->id];
        $base = ['lock_version' => 2, 'confirm' => 1, 'quantities' => [$a => '0', $b => '0']];
        $invalid = [
            ['released_as' => [$a => 'Generic example 500 mg']],
            ['quantities' => [$a => '6', $b => '0'], 'outcomes' => [$a => 'BUY_OUTSIDE']],
            ['outcome_notes' => [$a => 'Out of stock']],
            ['outcomes' => [$a => 'RETURNED']],
            ['released_as' => [$a => str_repeat('x', 151)]],
            [],
        ];
        foreach ($invalid as $extra) {
            $this->post($this->pharmacyUrl(), array_replace($base, $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('dispensings', 0);

        // Medicine A: 2 released as a substitute, the remaining 4 bought outside. Medicine B: declined.
        $this->post($this->pharmacyUrl(), array_replace($base, [
            'quantities' => [$a => '2', $b => '0'],
            'released_as' => [$a => 'Generic example 500 mg'],
            'outcomes' => [$a => 'BUY_OUTSIDE', $b => 'DECLINED'],
            'outcome_notes' => [$a => 'Out of stock at RHU', $b => ''],
        ]))->assertSessionHasNoErrors()->assertSessionHas('status', 'Medicine release recorded.');
        $this->assertDatabaseHas('dispensing_items', ['prescription_item_id' => $a, 'quantity_dispensed' => '2.00', 'released_as' => 'Generic example 500 mg', 'remainder_outcome' => 'BUY_OUTSIDE', 'quantity_closed' => '4.00', 'outcome_note' => 'Out of stock at RHU']);
        $this->assertDatabaseHas('dispensing_items', ['prescription_item_id' => $b, 'quantity_dispensed' => '0.00', 'remainder_outcome' => 'DECLINED', 'quantity_closed' => '2.00']);
        $this->assertSame('Fictional medicine A', $rx->fresh()->items[0]->medicine_name);
        $this->get($this->pharmacyUrl())->assertOk()
            ->assertSee('Pharmacy complete: some medicines not released here')
            ->assertSee('as Generic example 500 mg')->assertSee('Patient will buy outside: 4.00')->assertSee('Patient declined: 2.00')
            ->assertSee('(Out of stock at RHU)', false)->assertDontSee('Record pharmacy entry');
        $this->assertSame(1, Prescription::has('releasedDispensings')->count());
        $this->assertSame(1, Prescription::withUnreleasedOutcome('DECLINED')->count());

        // Nothing is waiting for release, so completion no longer needs an explanation.
        $this->actingAs($this->officer)->get(route('visits.completion', [$this->patient, $this->visit]))->assertOk()
            ->assertSee('Issued: pharmacy complete; some medicines to buy outside or declined')->assertDontSee('Some medicines have not been released');

        // Voiding the entry reopens both the released and the closed quantities.
        $pharmacist = User::role('Pharmacist')->latest('id')->firstOrFail();
        $release = $rx->dispensings()->firstOrFail();
        $this->actingAs($pharmacist)->post(route('pharmacy.void', [$this->patient, $this->visit, $release]), ['confirm' => 1, 'reason' => 'Fictional wrong entry', 'lock_version' => 3])->assertSessionHasNoErrors();
        $this->get($this->pharmacyUrl())->assertSee('Not dispensed')->assertSee('Record pharmacy entry');

        // An outcome-only entry is recorded but does not count as a release.
        $this->post($this->pharmacyUrl(), array_replace($base, ['lock_version' => 4, 'outcomes' => [$b => 'BUY_OUTSIDE']]))
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Pharmacy outcome recorded. No medicine was released in this entry.');
        $this->assertSame(0, Prescription::has('releasedDispensings')->count());
        $this->assertSame(1, Prescription::has('activeDispensings')->count());
        $this->assertSame(['visit_id', 'prescription_id', 'version'], array_keys(Activity::where('description', 'dispensing.recorded')->latest('id')->firstOrFail()->properties->all()));
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
