<?php

namespace Tests\Feature;

use App\Models\EligibilityCheck;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EligibilityTest extends TestCase
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
        $this->officer->assignRole('System Admin');
        $this->actingAs($this->officer);
    }

    private function url(?Patient $patient = null): string
    {
        return route('eligibility.save', [$patient ?? $this->patient, $this->visit]);
    }

    public function test_officer_records_and_edits_result_without_clinical_access(): void
    {
        $this->get(route('eligibility.index'))->assertOk()->assertSee('Fictional Verification')->assertSee('Not checked');
        $this->get($this->url())->assertOk()->assertSee('PhilHealth confirmation');
        $this->put($this->url(), ['lock_version' => 0, 'philhealth_confirmed' => true, 'remarks' => '<script>example</script>'])->assertSessionHasNoErrors()->assertRedirect();
        $record = EligibilityCheck::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals($this->officer->id, $record->verified_by);
        $originalTime = $record->verified_at;
        $this->get($this->url())->assertOk()->assertSee('&lt;script&gt;example&lt;/script&gt;', false)->assertDontSee('<script>example</script>', false);
        $this->get(route('eligibility.index'))->assertSee('With record');
        $second = User::factory()->create();
        $second->assignRole('System Admin');
        $this->actingAs($second)->put($this->url(), ['lock_version' => 1, 'philhealth_confirmed' => false])->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertEquals(2, $record->lock_version);
        $this->assertEquals($this->officer->id, $record->verified_by);
        $this->assertEquals($second->id, $record->updated_by);
        $this->assertTrue($originalTime->equalTo($record->verified_at));
        $this->assertNull($record->remarks);
        $this->assertFalse($record->philhealth_confirmed);
        $this->get(route('eligibility.index'))->assertSee('No record');
        $audit = Activity::where('description', 'eligibility.updated')->firstOrFail();
        $this->assertSame(['patient_id', 'visit_id', 'version'], array_keys($audit->properties->all()));
        $this->assertDatabaseCount('eligibility_checks', 1);
        $this->assertSame('OPEN', $this->visit->fresh()->status);
        $this->get('/patients')->assertForbidden();
        $this->get('/assessments')->assertForbidden();
    }

    public function test_stale_create_and_update_are_rejected(): void
    {
        $data = ['lock_version' => 0, 'philhealth_confirmed' => true];
        $this->put($this->url(), $data)->assertSessionHasNoErrors();
        $this->put($this->url(), $data)->assertSessionHasErrors('lock_version');
        $this->put($this->url(), ['lock_version' => 1, 'philhealth_confirmed' => false])->assertSessionHasNoErrors();
        $this->put($this->url(), ['lock_version' => 1, 'philhealth_confirmed' => true])->assertSessionHasErrors('lock_version');
        $this->assertDatabaseHas('eligibility_checks', ['philhealth_confirmed' => false, 'lock_version' => 2]);
        $this->assertDatabaseCount('eligibility_checks', 1);
    }

    public function test_validation_and_protected_fields(): void
    {
        $this->put($this->url(), ['lock_version' => 0])->assertSessionHasErrors('philhealth_confirmed');
        foreach ([
            ['philhealth_confirmed' => null], ['philhealth_confirmed' => 'yes'], ['status' => 'tampered'],
            ['remarks' => str_repeat('x', 1001)], ['lock_version' => -1],
            ['patient_id' => 999], ['visit_id' => 999], ['verified_by' => 999],
            ['verified_at' => '2000-01-01'], ['updated_by' => 999],
        ] as $extra) {
            $this->put($this->url(), array_replace(['lock_version' => 0, 'philhealth_confirmed' => true], $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('eligibility_checks', 0);
    }

    public function test_old_unchecked_results_require_a_new_selection(): void
    {
        $this->put($this->url(), ['lock_version' => 0, 'philhealth_confirmed' => '0', 'remarks' => 'Fictional previous check'])->assertSessionHasNoErrors();
        $record = EligibilityCheck::firstOrFail();
        $migration = require database_path('migrations/2026_09_25_230000_reset_unconfirmed_philhealth_results.php');
        $migration->up();
        $this->assertNull($record->fresh()->philhealth_confirmed);
        $this->assertSame('Fictional previous check', $record->fresh()->remarks);
        $this->get(route('eligibility.index'))->assertSee('Not checked');
        $this->put($this->url(), ['lock_version' => 1, 'philhealth_confirmed' => '0'])->assertSessionHasErrors('lock_version');
        $this->put($this->url(), ['lock_version' => 2, 'philhealth_confirmed' => '0'])->assertSessionHasNoErrors();
        $this->get(route('eligibility.index'))->assertSee('No record');
        $this->put($this->url(), ['lock_version' => 3, 'philhealth_confirmed' => '1'])->assertSessionHasNoErrors();
        $migration->up();
        $this->assertTrue($record->fresh()->philhealth_confirmed);
    }

    public function test_cross_patient_closed_visit_and_denied_roles(): void
    {
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-FICTIONAL-OTHER';
        $other->save();
        $data = ['lock_version' => 0, 'philhealth_confirmed' => true];
        $this->get($this->url($other))->assertNotFound();
        $this->put($this->url($other), $data)->assertNotFound();
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->put($this->url(), $data)->assertSessionHasErrors('visit');
        foreach (['Information Staff', 'Doctor / Medical Officer', 'Nurse', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('eligibility.index'))->assertForbidden();
            $this->get($this->url())->assertForbidden();
            $this->put($this->url(), $data)->assertForbidden();
        }
        $this->assertDatabaseCount('eligibility_checks', 0);
    }

    public function test_date_filter_view_only_and_archived_patient(): void
    {
        $this->get(route('eligibility.index', ['date' => '2000-01-01']))->assertOk()->assertDontSee('Fictional Verification');
        $reader = User::factory()->create();
        $reader->givePermissionTo('eligibility.view');
        $this->actingAs($reader)->get($this->url())->assertOk()->assertSee('read-only');
        $this->put($this->url(), ['lock_version' => 0, 'philhealth_confirmed' => true])->assertForbidden();
        $this->patient->delete();
        $this->get($this->url())->assertNotFound();
        $this->get(route('eligibility.index'))->assertDontSee('Fictional Verification');
    }

    public function test_legacy_role_consolidation_preserves_users_and_is_idempotent(): void
    {
        $legacy = Role::findOrCreate('IT / PhilHealth Staff', 'web');
        $user = User::factory()->create();
        $user->assignRole([$legacy, 'Nurse']);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $user->refresh();
        $this->assertTrue($user->hasAllRoles(['System Admin', 'Nurse']));
        $this->assertTrue($user->can('eligibility.verify'));
        $this->assertTrue($user->can('users.manage'));
        $this->assertDatabaseMissing('roles', ['name' => 'IT / PhilHealth Staff']);
        $this->assertEquals(1, Activity::where('description', 'staff.role_consolidated')->where('subject_id', $user->id)->count());
    }

    public function test_legacy_result_is_preserved_without_automatic_confirmation(): void
    {
        $record = new EligibilityCheck;
        $record->visit_id = $this->visit->id;
        $record->status = 'Fictional legacy text';
        $record->verified_by = $this->officer->id;
        $record->updated_by = $this->officer->id;
        $record->verified_at = now();
        $record->save();
        $this->get($this->url())->assertOk()->assertSee('Fictional legacy text')->assertSee('not been converted automatically');
        $this->assertNull($record->fresh()->philhealth_confirmed);
        $this->put($this->url(), ['lock_version' => 1, 'philhealth_confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($record->fresh()->philhealth_confirmed);
        $this->assertEquals('Fictional legacy text', $record->fresh()->status);
    }
}
