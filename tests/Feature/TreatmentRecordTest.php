<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class TreatmentRecordTest extends TestCase
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
        $this->post(route('patients.visits.store', $this->patient), ['queue_reference' => 'TEST-ITR-001', 'service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->officer = User::factory()->create();
        $this->officer->assignRole('Doctor / Medical Officer');
        $this->actingAs($this->officer);
    }

    private function url(?Patient $patient = null): string
    {
        return route('itr.edit', [$patient ?? $this->patient, $this->visit]);
    }

    public function test_doctor_creates_and_updates_itr_with_unknown_answers_and_private_audit(): void
    {
        $this->get($this->url())->assertOk()->assertSee('Individual Treatment Record')->assertSee('Family History of Hypertension');
        $data = ['lock_version' => 0, 'vitals_version' => 0, 'asthma' => '0', 'assessment' => '<script>fictional</script>'];
        $this->put($this->url(), $data)->assertSessionHasNoErrors()->assertRedirect();
        $record = TreatmentRecord::firstOrFail();
        $this->assertFalse($record->asthma);
        $this->assertNull($record->chest_pain);
        $this->assertEquals($this->officer->id, $record->created_by);
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->get($this->url())->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $this->put($this->url(), ['lock_version' => 1, 'vitals_version' => 0, 'planning' => 'Fictional plan'])->assertSessionHasNoErrors();
        $this->assertEquals(2, $record->fresh()->lock_version);
        $audit = Activity::where('description', 'itr.updated')->firstOrFail();
        $this->assertSame(['patient_id', 'visit_id', 'version'], array_keys($audit->properties->all()));
        $this->put($this->url(), $data)->assertSessionHasErrors('lock_version');
        $this->assertDatabaseCount('treatment_records', 1);
    }

    public function test_new_vitals_snapshot_and_stale_measurements(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-25 01:00:00', 'UTC'));
        $nurse = User::factory()->create();
        $nurse->assignRole('Nurse');
        $vitalsUrl = route('assessments.save', [$this->patient, $this->visit]);
        $this->actingAs($nurse)->put($vitalsUrl, ['lock_version' => 0, 'respiratory_rate' => 18, 'oxygen_saturation' => 98])->assertSessionHasNoErrors();
        $this->travel(10)->minutes();
        $data = ['lock_version' => 0, 'vitals_version' => 1, 'assessment' => 'Fictional assessment'];
        $this->actingAs($this->officer)->put($this->url(), $data)->assertSessionHasNoErrors();
        $record = TreatmentRecord::firstOrFail();
        $this->assertEquals(98, $record->objective_snapshot['oxygen_saturation']);
        $this->get($this->url())->assertSee('Current measurements last saved: Sep 25, 2026 9:00:00 AM')
            ->assertSee('ITR last saved: Sep 25, 2026 9:10:00 AM');
        $this->travel(20)->minutes();
        $this->actingAs($nurse)->put($vitalsUrl, ['lock_version' => 1, 'oxygen_saturation' => 97])->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->put($this->url(), array_replace($data, ['lock_version' => 1]))->assertSessionHasErrors('lock_version');
        $this->assertEquals(98, $record->fresh()->objective_snapshot['oxygen_saturation']);
        $this->get($this->url())->assertOk()->assertSee('Objective values at last ITR save')
            ->assertSee('Current measurements last saved: Sep 25, 2026 9:30:00 AM')
            ->assertSee('ITR last saved: Sep 25, 2026 9:10:00 AM');
        $this->travel(5)->minutes();
        $this->put($this->url(), array_replace($data, ['lock_version' => 1, 'vitals_version' => 2]))->assertSessionHasNoErrors();
        $this->get($this->url())->assertSee('Current measurements last saved: Sep 25, 2026 9:30:00 AM')
            ->assertSee('ITR last saved: Sep 25, 2026 9:35:00 AM');
        $this->travelBack();
    }

    public function test_denied_roles_ownership_closed_visit_and_validation(): void
    {
        $data = ['lock_version' => 0, 'vitals_version' => 0, 'assessment' => 'Example'];
        foreach ([
            ['asthma' => 'maybe'], ['lmp' => '2999-01-01'], ['assessment' => str_repeat('x', 5001)],
            ['visit_id' => 999], ['created_by' => 999], ['objective_snapshot' => ['temperature' => 40]],
            ['taking_medicine' => 0, 'medicine_details' => 'Example'],
        ] as $extra) {
            $this->put($this->url(), array_replace($data, $extra))->assertSessionHasErrors();
        }
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-FICTIONAL-OTHER';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        $this->put($this->url($other), $data)->assertNotFound();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get($this->url())->assertForbidden();
            $this->put($this->url(), $data)->assertForbidden();
        }
        $this->actingAs($this->officer);
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->put($this->url(), $data)->assertSessionHasErrors('visit');
        $this->assertDatabaseCount('treatment_records', 0);
    }

    public function test_blank_itr_read_only_access_and_measurement_limits(): void
    {
        $this->put($this->url(), ['lock_version' => 0, 'vitals_version' => 0])->assertSessionHasErrors('record');
        $reader = User::factory()->create();
        $reader->givePermissionTo('consultations.view');
        $this->actingAs($reader)->get($this->url())->assertOk()->assertSee('read-only');
        $this->put($this->url(), ['lock_version' => 0, 'vitals_version' => 0, 'assessment' => 'Example'])->assertForbidden();
        $nurse = User::factory()->create();
        $nurse->assignRole('Nurse');
        $url = route('assessments.save', [$this->patient, $this->visit]);
        $this->actingAs($nurse)->put($url, ['lock_version' => 0, 'oxygen_saturation' => 101])->assertSessionHasErrors('oxygen_saturation');
        $this->put($url, ['lock_version' => 0, 'respiratory_rate' => 1.5])->assertSessionHasErrors('respiratory_rate');
        $this->put($url, ['lock_version' => 0, 'oxygen_saturation' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vital_signs', ['oxygen_saturation' => 0]);
    }

    public function test_doctor_diagnoses_are_validated_deduplicated_scoped_and_audited_privately(): void
    {
        $data = ['lock_version' => 0, 'vitals_version' => 0, 'diagnoses' => "Fictional condition A\n fictional   CONDITION a \nFictional condition B"];
        $this->put($this->url(), $data)->assertSessionHasNoErrors();
        $record = TreatmentRecord::firstOrFail();
        $this->assertCount(2, $record->diagnoses);
        $this->assertStringNotContainsString('Fictional condition', Activity::where('description', 'itr.created')->firstOrFail()->properties->toJson());
        $this->put($this->url(), $data)->assertSessionHasErrors('lock_version');
        $data['lock_version'] = 1;
        $this->put($this->url(), array_replace($data, ['diagnoses' => str_repeat('a', 151)]))->assertSessionHasErrors('diagnoses');
        $this->put($this->url(), array_replace($data, ['diagnoses' => implode("\n", range(1, 11))]))->assertSessionHasErrors('diagnoses');
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin)->put($this->url(), $data)->assertForbidden();
        $this->actingAs($this->officer);
        $other = new Patient(['first_name' => 'Another fictional', 'last_name' => 'Patient']);
        $other->patient_number = 'DIAGNOSIS-TEST';
        $other->created_by = $this->officer->id;
        $other->duplicate_key = Patient::duplicateKey($other->first_name, $other->last_name);
        $other->save();
        $this->put($this->url($other), $data)->assertNotFound();
        $this->put($this->url(), ['lock_version' => 1, 'vitals_version' => 0, 'assessment' => 'Preserve diagnoses for older clients'])->assertSessionHasNoErrors();
        $this->assertCount(2, $record->fresh()->diagnoses);
        $this->put($this->url(), ['lock_version' => 2, 'vitals_version' => 0, 'assessment' => 'Clear diagnoses explicitly', 'diagnoses' => ''])->assertSessionHasNoErrors();
        $this->assertSame([], $record->fresh()->diagnoses);
    }
}
