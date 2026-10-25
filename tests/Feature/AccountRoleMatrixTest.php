<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AccountRoles;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AccountRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['primary_role' => 'System Admin']);
        $admin->assignRole('System Admin');
        $this->actingAs($admin);
    }

    public function test_every_primary_role_accepts_only_its_approved_additional_roles(): void
    {
        foreach (AccountRoles::ADDITIONAL as $primary => $allowed) {
            $target = User::factory()->create(['primary_role' => $primary]);
            $target->assignRole($primary);
            foreach (array_keys(AccountRoles::ADDITIONAL) as $additional) {
                if ($additional === $primary) {
                    continue;
                }
                $before = Activity::count();
                $response = $this->patch(route('staff.update', $target), ['is_active' => true, 'roles' => [$primary, $additional]]);
                if (in_array($additional, $allowed, true)) {
                    $response->assertSessionHasNoErrors()->assertRedirect('/staff');
                    $this->assertTrue($target->fresh()->hasRole($additional));
                } else {
                    $response->assertSessionHasErrors('roles');
                    $this->assertFalse($target->fresh()->hasRole($additional));
                    $this->assertSame($before, Activity::count());
                }
            }
            $this->patch(route('staff.update', $target), ['is_active' => true, 'roles' => [$primary]])->assertSessionHasNoErrors();
            $this->assertSame([$primary], $target->fresh()->getRoleNames()->all());
        }
    }

    public function test_primary_role_cannot_be_changed_removed_or_used_to_escalate(): void
    {
        $target = User::factory()->create(['primary_role' => 'Nurse']);
        $target->assignRole('Nurse');
        $this->patch(route('staff.update', $target), ['primary_role' => 'Doctor / Medical Officer', 'roles' => ['Doctor / Medical Officer'], 'is_active' => true])->assertSessionHasErrors('primary_role');
        $this->patch(route('staff.update', $target), ['roles' => ['Doctor / Medical Officer'], 'is_active' => true])->assertSessionHasErrors('roles');
        $this->assertSame('Nurse', $target->fresh()->primary_role);
        $this->assertSame(['Nurse'], $target->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_legacy_multi_role_account_requires_explicit_existing_primary_and_then_locks_it(): void
    {
        $target = User::factory()->create();
        $target->assignRole(['Nurse', 'Doctor / Medical Officer']);
        $this->patch(route('staff.update', $target), ['roles' => ['Nurse'], 'is_active' => true])->assertSessionHasErrors('primary_role');
        $this->patch(route('staff.update', $target), ['primary_role' => 'System Admin', 'roles' => ['System Admin'], 'is_active' => true])->assertSessionHasErrors('primary_role');
        $this->patch(route('staff.update', $target), ['primary_role' => 'Doctor / Medical Officer', 'roles' => ['Doctor / Medical Officer', 'Nurse'], 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame('Doctor / Medical Officer', $target->fresh()->primary_role);
        $this->patch(route('staff.update', $target), ['primary_role' => 'Nurse', 'roles' => ['Nurse'], 'is_active' => true])->assertSessionHasErrors('primary_role');
        $this->assertSame('Doctor / Medical Officer', Activity::first()->properties['after']['primary_role']);
    }

    public function test_non_admin_with_management_permissions_still_cannot_assign_roles(): void
    {
        $doctor = User::factory()->create(['primary_role' => 'Doctor / Medical Officer']);
        $doctor->assignRole(['Doctor / Medical Officer', 'Nurse']);
        $doctor->givePermissionTo(['users.manage', 'roles.manage']);
        $this->actingAs($doctor)->get('/staff')->assertForbidden();
        $this->patch(route('staff.update', $doctor), ['roles' => ['System Admin'], 'is_active' => true])->assertForbidden();
    }

    public function test_creation_rejects_forbidden_additional_roles(): void
    {
        $data = ['name' => 'Fictional Account', 'email' => 'matrix@example.test', 'password' => 'Test123!', 'password_confirmation' => 'Test123!', 'primary_role' => 'System Admin', 'roles' => ['System Admin', 'Doctor / Medical Officer']];
        $this->post('/staff', $data)->assertSessionHasErrors('roles');
        $this->assertDatabaseCount('users', 1);
        $this->post('/staff', [...$data, 'roles' => ['System Admin', 'Information Staff']])->assertSessionHasNoErrors()->assertRedirect('/staff');
        $created = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('System Admin', $created->primary_role);
        $this->assertTrue($created->can('patients.create'));
        $this->assertFalse($created->can('itr.create'));
    }

    public function test_migration_backfills_only_unambiguous_roles_without_changing_access(): void
    {
        $single = User::factory()->create();
        $single->assignRole('Nurse');
        $multiple = User::factory()->create();
        $multiple->assignRole(['System Admin', 'Doctor / Medical Officer']);
        $migration = require database_path('migrations/2026_10_24_150000_add_primary_role_to_users.php');
        $migration->down();
        $migration->up();
        $this->assertSame('Nurse', $single->fresh()->primary_role);
        $this->assertNull($multiple->fresh()->primary_role);
        $this->assertTrue($multiple->fresh()->hasAllRoles(['System Admin', 'Doctor / Medical Officer']));
    }
}
