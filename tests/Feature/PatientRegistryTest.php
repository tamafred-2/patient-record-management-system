<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
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
        return array_replace(['first_name' => 'Fictional', 'last_name' => 'Sample', 'birth_date' => '2000-01-10', 'barangay' => 'Example Barangay', 'sex' => 'Female', 'municipality' => 'Calasiao', 'province' => 'Pangasinan'], $extra);
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
        $this->put(route('patients.update', $patient), $this->data(['sex' => ' ', 'barangay' => '', 'municipality' => '', 'province' => '', 'lock_version' => 1]))
            ->assertSessionHasErrors(['sex', 'barangay', 'municipality', 'province']);
        $this->assertSame(1, $patient->fresh()->lock_version);
        $this->put(route('patients.update', $patient), $this->data(['barangay' => 'Changed Barangay', 'lock_version' => 1]))->assertRedirect(route('patients.show', $patient));
        $this->assertSame('Changed Barangay', $patient->fresh()->barangay);
        $this->assertSame($patient->patient_number, $patient->fresh()->patient_number);
        $this->assertSame(2, $patient->fresh()->lock_version);
    }

    public function test_sex_and_address_are_required_and_future_birth_dates_are_rejected(): void
    {
        $this->actingAs($this->staff());
        $this->post('/patients', ['first_name' => 'Only', 'last_name' => 'Names'])->assertSessionHasErrors(['sex', 'barangay', 'municipality', 'province', 'birth_date']);
        $this->post('/patients', $this->data(['birth_date' => null]))->assertSessionHasErrors('birth_date');
        $this->post('/patients', $this->data())->assertRedirect();
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
        $this->post('/patients', $this->data(['first_name' => ' fictional ', 'last_name' => 'SAMPLE', 'contact_number' => '00000000000']))
            ->assertStatus(422)->assertSee('Possible duplicate profiles')->assertSee($first->patient_number)->assertSee('00000000000');
        $this->assertDatabaseCount('patients', 1);
        $second = $this->register(['birth_date' => '2001-01-10', 'confirm_distinct' => true]);
        $this->assertNotSame($first->patient_number, $second->patient_number);
        $this->assertTrue(Activity::latest('id')->first()->properties['duplicate_review_confirmed']);
    }

    public function test_edit_duplicate_warning_excludes_the_current_patient(): void
    {
        $this->actingAs($this->staff());
        $first = $this->register();
        $second = $this->register(['first_name' => 'Another']);
        $this->put(route('patients.update', $second), $this->data(['birth_date' => '2001-01-10', 'lock_version' => 1]))->assertStatus(422)->assertSee($first->patient_number);
        $this->assertSame('Another', $second->fresh()->first_name);
        $this->put(route('patients.update', $second), $this->data(['birth_date' => '2001-01-10', 'lock_version' => 1, 'confirm_distinct' => true]))->assertRedirect();
        $this->assertSame('Fictional', $second->fresh()->first_name);
    }

    public function test_exact_duplicate_is_blocked_even_with_confirmation_and_preserves_input(): void
    {
        $this->actingAs($this->staff());
        $first = $this->register();
        foreach ([false, true] as $confirmed) {
            $this->post('/patients', $this->data([
                'first_name' => ' FICTIONAL ', 'barangay' => 'Example   Barangay',
                'middle_name' => '', 'guardian_name' => '', 'confirm_distinct' => $confirmed,
            ]))->assertStatus(422)->assertSee('Patient already exists')
                ->assertSee(route('patients.show', $first))->assertSee('FICTIONAL')
                ->assertDontSee('name="confirm_distinct"', false);
        }
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('activity_log', 1);

        $second = $this->register(['first_name' => 'Another']);
        $this->put(route('patients.update', $second), $this->data(['lock_version' => 1, 'confirm_distinct' => true]))
            ->assertStatus(422)->assertSee('Patient already exists');
        $this->assertSame('Another', $second->fresh()->first_name);
        $this->assertSame(1, $second->fresh()->lock_version);
        $this->assertDatabaseCount('activity_log', 2);
        $this->put(route('patients.update', $first), $this->data(['lock_version' => 1]))
            ->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $first));
    }

    public function test_exact_match_is_checked_beyond_the_ten_displayed_name_matches(): void
    {
        $this->actingAs($this->staff());
        for ($index = 1; $index <= 11; $index++) {
            $this->register(['barangay' => 'Example Barangay '.$index, 'confirm_distinct' => true]);
        }
        $this->post('/patients', $this->data(['barangay' => 'Example Barangay 11', 'confirm_distinct' => true]))
            ->assertStatus(422)->assertSee('Patient already exists');
        $this->assertDatabaseCount('patients', 11);
        $this->assertDatabaseCount('activity_log', 11);
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

    public function test_registry_filters_by_sex_age_group_barangay_and_visits_with_search(): void
    {
        $this->travelTo(now('Asia/Manila')->setDate(2026, 10, 25)->setTime(9, 0));
        $staff = $this->staff();
        $this->actingAs($staff);
        $child = $this->register(['first_name' => 'Filter', 'last_name' => 'Child', 'birth_date' => '2015-05-01', 'sex' => 'Male', 'barangay' => 'Alpha']);
        $adult = $this->register(['first_name' => 'Filter', 'last_name' => 'Adult', 'birth_date' => '1990-05-01', 'sex' => 'Female', 'barangay' => 'Beta']);
        $senior = $this->register(['first_name' => 'Filter', 'last_name' => 'Senior', 'birth_date' => '1966-10-25', 'sex' => 'Female', 'barangay' => 'Alpha']);
        $service = Service::create(['name' => 'Consultation', 'code' => 'CONSULT_FILTER']);
        Visit::forceCreate(['patient_id' => $adult->id, 'service_id' => $service->id, 'created_by' => $staff->id, 'visit_date' => '2026-10-25', 'status' => Visit::OPEN]);
        Visit::forceCreate(['patient_id' => $senior->id, 'service_id' => $service->id, 'created_by' => $staff->id, 'visit_date' => '2026-10-20', 'status' => Visit::COMPLETED]);

        $this->get('/patients')->assertOk()->assertSee('Age group')->assertSee('<option value="Alpha"', false)->assertSee('<option value="Beta"', false);
        $this->get('/patients?sex=Female')->assertSee($adult->patient_number)->assertSee($senior->patient_number)->assertDontSee($child->patient_number);
        // Turning 60 today counts as a senior citizen; a 17-year-old is a child.
        $this->get('/patients?age=senior')->assertSee($senior->patient_number)->assertDontSee($adult->patient_number)->assertDontSee($child->patient_number);
        $this->get('/patients?age=child')->assertSee($child->patient_number)->assertDontSee($adult->patient_number);
        $this->get('/patients?age=adult')->assertSee($adult->patient_number)->assertDontSee($senior->patient_number)->assertDontSee($child->patient_number);
        $this->get('/patients?barangay=Alpha')->assertSee($child->patient_number)->assertSee($senior->patient_number)->assertDontSee($adult->patient_number);
        $this->get('/patients?visits=open')->assertSee($adult->patient_number)->assertDontSee($senior->patient_number)->assertDontSee($child->patient_number);
        $this->get('/patients?visits=none')->assertSee($child->patient_number)->assertDontSee($adult->patient_number)->assertDontSee($senior->patient_number);
        $this->get('/patients?q=filter&sex=Female&barangay=Alpha')->assertSee($senior->patient_number)->assertDontSee($adult->patient_number)->assertSee('1 patient found');
        $this->get('/patients?sex=Unknown')->assertSessionHasErrors('sex');
        $this->get('/patients?age=elderly')->assertSessionHasErrors('age');
        $this->get('/patients?visits=all')->assertSessionHasErrors('visits');
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

    public function test_optional_guardian_validation_and_clear_without_logging_values(): void
    {
        $this->actingAs($this->staff());
        foreach ([['guardian_name' => 'Fictional Guardian'], ['guardian_phone' => '00000000000'], ['guardian_relationship' => 'Parent']] as $partial) {
            $this->post('/patients', $this->data($partial))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('patients', 0);
        $patient = $this->register([
            'emergency_contact_name' => 'Fictional Guardian',
            'emergency_contact_number' => '00000000000',
            'guardian_relationship' => 'Parent',
            'guardian_name' => 'Fictional Guardian',
            'guardian_phone' => '00000000000',
        ]);
        $this->assertSame('Fictional Guardian', $patient->guardian_name);
        $this->assertSame('00000000000', $patient->guardian_phone);
        $this->get(route('patients.show', $patient))->assertSee('Fictional Guardian')->assertSee('Parent');
        $this->assertStringNotContainsString('Fictional Guardian', Activity::latest('id')->first()->toJson());
        $this->put(route('patients.update', $patient), $this->data([
            'lock_version' => 1, 'guardian_name' => '', 'guardian_relationship' => '', 'guardian_phone' => '',
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($patient->fresh()->guardian_name);
        $this->assertSame('Fictional Guardian', $patient->fresh()->emergency_contact_name);
    }

    public function test_contact_numbers_require_exactly_eleven_digits_on_create_and_update(): void
    {
        $this->actingAs($this->staff());
        $phones = ['contact_number', 'emergency_contact_number', 'guardian_phone'];
        $valid = array_fill_keys($phones, '00000000000');
        $patient = $this->register([...$valid, 'guardian_name' => 'Fictional Guardian', 'guardian_relationship' => 'Parent']);
        $original = $patient->getAttributes();

        foreach ($phones as $field) {
            foreach (['0', '0000000000', '000000000000', '0000000000a', '00000-00000', '+63000000000', '00000 00000', '０００００００００００'] as $value) {
                $data = $this->data(['guardian_name' => 'Fictional Guardian', 'guardian_relationship' => 'Parent', $field => $value]);
                $this->post('/patients', $data)->assertSessionHasErrors($field);
                $this->put(route('patients.update', $patient), [...$data, 'lock_version' => 1])->assertSessionHasErrors($field);
            }
            $this->assertSame('00000000000', $patient->fresh()->{$field});
        }
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('activity_log', 1);
        $this->assertSame($original, $patient->fresh()->getAttributes());

        $this->put(route('patients.update', $patient), $this->data([
            ...array_fill_keys($phones, ''), 'lock_version' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        foreach ($phones as $field) {
            $this->assertNull($patient->fresh()->{$field});
        }
    }

    public function test_name_digits_are_rejected_on_registration_and_update_without_changes(): void
    {
        $this->actingAs($this->staff());
        $patient = $this->register();
        $original = $patient->getAttributes();
        $fields = ['first_name', 'last_name', 'middle_name', 'suffix', 'emergency_contact_name', 'guardian_name', 'guardian_relationship'];

        foreach (['0', ' 0 ', 'Sample123', 'Sample１２', 'Sample١٢'] as $value) {
            foreach ($fields as $field) {
                $data = $this->data(['guardian_name' => 'Fictional Guardian', 'guardian_relationship' => 'Parent', $field => $value]);
                $this->post('/patients', $data)->assertSessionHasErrors($field);
                $this->put(route('patients.update', $patient), [...$data, 'lock_version' => 1])
                    ->assertSessionHasErrors($field);
            }
        }

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('activity_log', 1);
        $this->assertSame($original, $patient->fresh()->getAttributes());
    }

    public function test_name_fields_reject_numbers(): void
    {
        $this->actingAs($this->staff());

        // First name with numbers
        $this->post('/patients', $this->data(['first_name' => 'Juan123']))
            ->assertSessionHasErrors(['first_name' => 'The first name must not contain numbers.']);

        // Last name with numbers
        $this->post('/patients', $this->data(['last_name' => 'Dela Cruz2']))
            ->assertSessionHasErrors(['last_name' => 'The last name must not contain numbers.']);

        // Middle name with numbers
        $this->post('/patients', $this->data(['middle_name' => 'Reyes3']))
            ->assertSessionHasErrors(['middle_name' => 'The middle name must not contain numbers.']);

        // Suffix with numbers
        $this->post('/patients', $this->data(['suffix' => '4th']))
            ->assertSessionHasErrors(['suffix' => 'The suffix must not contain numbers.']);

        // Emergency contact name with numbers
        $this->post('/patients', $this->data(['emergency_contact_name' => 'Adrian 5']))
            ->assertSessionHasErrors(['emergency_contact_name' => 'The emergency contact name must not contain numbers.']);

        // Guardian name with numbers
        $this->post('/patients', $this->data(['guardian_name' => 'Maria 6', 'guardian_relationship' => 'Mother']))
            ->assertSessionHasErrors(['guardian_name' => 'The guardian name must not contain numbers.']);

        // Guardian relationship with numbers
        $this->post('/patients', $this->data(['guardian_name' => 'Maria', 'guardian_relationship' => 'Mother7']))
            ->assertSessionHasErrors(['guardian_relationship' => 'The guardian relationship must not contain numbers.']);

        // Valid names without numbers (including hyphens, spaces, apostrophes, periods, and accents)
        $this->post('/patients', $this->data([
            'first_name' => 'Mary-Ann',
            'last_name' => "D'Angelo",
            'middle_name' => 'Ma. Cristina',
            'suffix' => 'Jr.',
            'emergency_contact_name' => 'Adrian Santos',
            'guardian_name' => 'Maria Niño',
            'guardian_relationship' => 'Mother',
        ]))->assertRedirect();

        $this->assertDatabaseHas('patients', [
            'first_name' => 'Mary-Ann',
            'last_name' => "D'Angelo",
        ]);

        $patient = Patient::sole();
        $this->put(route('patients.update', $patient), $this->data([
            'first_name' => ' José ',
            'last_name' => 'Dela Cruz',
            'middle_name' => ' ',
            'suffix' => 'III',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'lock_version' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $patient));
        $this->assertSame('José', $patient->fresh()->first_name);
        $this->assertNull($patient->fresh()->middle_name);
        $this->assertSame('III', $patient->fresh()->suffix);
    }
}
