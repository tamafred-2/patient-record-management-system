<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PatientRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function staff(string $role = 'Information Staff'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function data(array $extra = []): array
    {
        return array_replace(['first_name' => 'Fictional', 'last_name' => 'Sample', 'birth_date' => '2000-01-10', 'barangay' => 'Example Barangay'], $extra);
    }

    private function register(array $extra = []): Patient
    {
        $this->post('/patients', $this->data($extra))->assertRedirect();

        return Patient::latest('id')->firstOrFail();
    }

    public function test_information_staff_can_register_search_view_and_update_demographics(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get('/patients/create')->assertOk();
        $patient = $this->register();
        $this->assertSame($staff->id, $patient->created_by);
        $this->assertMatchesRegularExpression('/^RHU-\d{4}-\d{6,}$/', $patient->patient_number);
        $this->get('/patients?q=sample+fictional')->assertOk()->assertSee($patient->patient_number);
        $this->get('/patients?q='.$patient->patient_number)->assertOk()->assertSee('Fictional Sample');
        $this->get(route('patients.show', $patient))->assertOk()->assertSee('Example Barangay');
        $this->get(route('patients.edit', $patient))->assertOk();
        $this->put(route('patients.update', $patient), $this->data(['barangay' => 'Changed Barangay', 'lock_version' => 1]))->assertRedirect(route('patients.show', $patient));
        $this->assertSame('Changed Barangay', $patient->fresh()->barangay);
        $this->assertSame($patient->patient_number, $patient->fresh()->patient_number);
        $this->assertSame(2, $patient->fresh()->lock_version);
    }

    public function test_names_only_are_allowed_but_future_birth_dates_are_rejected(): void
    {
        $this->actingAs($this->staff());
        $this->post('/patients', ['first_name' => 'Only', 'last_name' => 'Names'])->assertRedirect();
        $this->assertNull(Patient::first()->birth_date);
        $this->post('/patients', $this->data(['birth_date' => now()->addDay()->format('Y-m-d')]))->assertSessionHasErrors('birth_date');
        $this->post('/patients', $this->data(['first_name' => '', 'last_name' => ['invalid']]))->assertSessionHasErrors(['first_name', 'last_name']);
        $this->post('/patients', $this->data(['birth_date' => '2000-02-31']))->assertSessionHasErrors('birth_date');
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_guests_and_unassigned_roles_cannot_read_or_mutate_patients(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $this->post('/logout');
        $this->get('/patients')->assertRedirect('/login');
        $this->post('/patients', $this->data())->assertRedirect('/login');
        foreach (['System Admin', 'Nurse', 'Doctor / Medical Officer', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $this->actingAs($this->staff($role));
            $this->get('/patients')->assertForbidden();
            $this->get('/patients/create')->assertForbidden();
            $this->get(route('patients.show', $patient))->assertForbidden();
            $this->get(route('patients.edit', $patient))->assertForbidden();
            $this->post('/patients', $this->data())->assertForbidden();
            $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1]))->assertForbidden();
        }
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_view_permission_does_not_allow_registration_or_editing(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $reader = User::factory()->create();
        $reader->givePermissionTo('patients.view');
        $this->actingAs($reader)->get(route('patients.show', $patient))->assertOk();
        $this->post('/patients', $this->data())->assertForbidden();
        $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1]))->assertForbidden();
    }

    public function test_duplicate_warning_preserves_input_and_requires_explicit_acknowledgement(): void
    {
        $this->actingAs($this->staff());
        $first = $this->register();
        $this->post('/patients', $this->data(['first_name' => ' fictional ', 'last_name' => 'SAMPLE', 'contact_number' => '000-TEST']))
            ->assertStatus(422)->assertSee('Possible duplicate profiles')->assertSee($first->patient_number)->assertSee('000-TEST');
        $this->assertDatabaseCount('patients', 1);
        $second = $this->register(['confirm_distinct' => true]);
        $this->assertNotSame($first->patient_number, $second->patient_number);
        $this->assertTrue(Activity::latest('id')->first()->properties['duplicate_review_confirmed']);
    }

    public function test_edit_duplicate_warning_excludes_the_current_patient(): void
    {
        $this->actingAs($this->staff());
        $first = $this->register();
        $second = $this->register(['first_name' => 'Another']);
        $this->put(route('patients.update', $second), $this->data(['lock_version' => 1]))->assertStatus(422)->assertSee($first->patient_number);
        $this->assertSame('Another', $second->fresh()->first_name);
        $this->put(route('patients.update', $second), $this->data(['lock_version' => 1, 'confirm_distinct' => true]))->assertRedirect();
        $this->assertSame('Fictional', $second->fresh()->first_name);
    }

    public function test_system_fields_and_cross_patient_body_ids_are_rejected(): void
    {
        $this->actingAs($this->staff());
        $first = $this->register();
        $second = $this->register(['first_name' => 'Another']);
        $this->post('/patients', $this->data(['patient_number' => 'RHU-TAMPERED', 'created_by' => 999]))->assertSessionHasErrors(['patient_number', 'created_by']);
        $this->put(route('patients.update', $first), $this->data(['lock_version' => 1, 'patient_id' => $second->id]))->assertSessionHasErrors('patient_id');
        $this->put(route('patients.update', $first), $this->data(['lock_version' => 1, 'patient_number' => $second->patient_number]))->assertSessionHasErrors('patient_number');
        $this->assertSame(1, $first->fresh()->lock_version);
        $this->assertSame(1, $second->fresh()->lock_version);
        $this->assertDatabaseCount('activity_log', 2);
    }

    public function test_stale_edits_do_not_overwrite_newer_changes(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1, 'barangay' => 'Latest']))->assertRedirect();
        $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1, 'barangay' => 'Stale']))->assertSessionHasErrors('lock_version');
        $this->assertSame('Latest', $patient->fresh()->barangay);
        $this->assertDatabaseCount('activity_log', 2);
    }

    public function test_audit_records_actor_subject_and_field_names_without_demographic_values(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff);
        $patient = $this->register();
        $audit = Activity::firstOrFail();
        $this->assertEquals($staff->id, $audit->causer_id);
        $this->assertEquals($patient->id, $audit->subject_id);
        $this->assertSame(Patient::class, $audit->subject_type);
        $this->assertContains('first_name', $audit->properties['fields']);
        $this->assertStringNotContainsString('Fictional', $audit->toJson());
        $this->assertStringNotContainsString('Example Barangay', $audit->toJson());
        $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1, 'barangay' => 'Changed']))->assertRedirect();
        $this->assertSame(['barangay'], Activity::latest('id')->first()->properties['fields']);
    }

    public function test_search_handles_literal_wildcards_and_empty_results(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $this->get('/patients?q=%25')->assertOk()->assertDontSee($patient->patient_number);
        $this->get('/patients?q=missing')->assertOk()->assertSee('No matching patients');
        $this->get('/patients?q[]=bad')->assertSessionHasErrors('q');
    }

    public function test_archived_profiles_are_not_listed_or_exposed_and_no_delete_route_exists(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $this->delete(route('patients.show', $patient))->assertStatus(405);
        $patient->delete();
        $this->get('/patients')->assertDontSee($patient->patient_number);
        $this->get(route('patients.show', $patient))->assertNotFound();
        $this->put(route('patients.update', $patient), $this->data(['lock_version' => 1]))->assertNotFound();
    }

    public function test_inactive_information_staff_cannot_register_patients(): void
    {
        $staff = $this->staff();
        $staff->is_active = false;
        $staff->save();
        $this->actingAs($staff)->post('/patients', $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('patients', 0);
    }
}
