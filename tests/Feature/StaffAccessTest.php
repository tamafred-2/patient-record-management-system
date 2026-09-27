<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffAccessTest extends TestCase
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

    private function accountData(): array
    {
        return ['name' => 'Fictional Staff', 'email' => 'new.staff@example.test', 'password' => 'fictional-password-123', 'password_confirmation' => 'fictional-password-123', 'roles' => ['Nurse', 'Midwife']];
    }

    public function test_guest_can_view_login_but_cannot_access_staff_routes(): void
    {
        $this->get('/login')->assertOk()->assertSee('Login');
        $this->get('/forgot-password')->assertOk()->assertSee('Contact your RHU system administrator');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/staff')->assertRedirect('/login');
        $this->post('/staff', $this->accountData())->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->accountData())->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_active_staff_can_log_in_with_normalized_email_and_log_out(): void
    {
        $user = $this->staff();
        $this->get('/login');
        $before = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->get('/dashboard')->assertOk()->assertSee($user->name)->assertDontSee('Manage user accounts');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_invalid_and_inactive_credentials_are_rejected_without_flashing_password(): void
    {
        $user = $this->staff();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $user->is_active = false;
        $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_malformed_login_input_is_validated(): void
    {
        $this->post('/login', ['email' => ['bad'], 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_five_failed_logins_temporarily_block_correct_credentials(): void
    {
        $user = $this->staff();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts.', session('errors')->first('email'));
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_logged_in_staff_lose_access(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->get('/dashboard')->assertOk();
        $user->is_active = false;
        $user->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_every_seeded_role_has_dashboard_but_only_admin_has_staff_access(): void
    {
        foreach (RolePermissionSeeder::ROLES as $role) {
            $user = $this->staff($role);
            $this->actingAs($user)->get('/dashboard')->assertOk();
            $response = $this->get('/staff');
            $role === 'System Admin' ? $response->assertOk() : $response->assertForbidden();
        }
    }

    public function test_user_without_dashboard_permission_is_denied(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
    }

    public function test_non_admin_cannot_create_or_change_another_account(): void
    {
        $actor = $this->staff();
        $target = $this->staff('Nurse');
        $this->actingAs($actor)->get('/staff/create')->assertForbidden();
        $this->get(route('staff.edit', $target))->assertForbidden();
        $this->post('/staff', $this->accountData())->assertForbidden();
        $this->patch(route('staff.update', $target), ['is_active' => false, 'roles' => ['System Admin']])->assertForbidden();
        $this->assertTrue($target->fresh()->is_active);
        $this->assertFalse($target->fresh()->hasRole('System Admin'));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_user_management_permission_alone_cannot_assign_roles(): void
    {
        $user = $this->staff();
        $user->givePermissionTo('users.manage');
        $this->actingAs($user)->post('/staff', $this->accountData())->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_create_a_multi_role_staff_account_and_audit_excludes_password(): void
    {
        $admin = $this->staff('System Admin');
        $this->actingAs($admin)->get('/staff/create')->assertOk();
        $data = $this->accountData();
        $this->post('/staff', [...$data, 'is_active' => false])->assertRedirect('/staff');
        $created = User::where('email', $data['email'])->firstOrFail();
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->hasAllRoles(['Nurse', 'Midwife']));
        $this->assertTrue(Hash::check($data['password'], $created->password));
        $audit = Activity::where('description', 'staff.created')->firstOrFail();
        $this->assertEquals($admin->id, $audit->causer_id);
        $this->assertEquals($created->id, $audit->subject_id);
        $this->assertStringNotContainsString($data['password'], $audit->toJson());
        $this->assertStringNotContainsString($created->password, $audit->toJson());
    }

    public function test_admin_can_change_roles_deactivate_and_reactivate_staff(): void
    {
        $admin = $this->staff('System Admin');
        $target = $this->staff();
        $this->actingAs($admin)->get(route('staff.edit', $target))->assertOk();
        $this->patch(route('staff.update', $target), ['is_active' => false, 'roles' => ['Nurse']])->assertRedirect('/staff');
        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue($target->fresh()->hasRole('Nurse'));
        $this->patch(route('staff.update', $target), ['is_active' => true, 'roles' => ['Nurse']])->assertRedirect('/staff');
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseCount('activity_log', 2);
    }

    public function test_admin_cannot_change_own_access(): void
    {
        $admin = $this->staff('System Admin');
        $this->actingAs($admin)->patch(route('staff.update', $admin), ['is_active' => false, 'roles' => ['Nurse']])->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('System Admin'));
    }

    public function test_account_validation_rejects_invalid_roles_passwords_and_duplicate_emails(): void
    {
        $admin = $this->staff('System Admin');
        $this->actingAs($admin)->post('/staff', [...$this->accountData(), 'roles' => ['Invented Role']])->assertSessionHasErrors('roles.0');
        $this->post('/staff', [...$this->accountData(), 'password' => 'short'])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
        $this->post('/staff', [...$this->accountData(), 'email' => strtoupper($admin->email)])->assertSessionHasErrors('email');
        $target = $this->staff();
        $this->patch(route('staff.update', $target), ['is_active' => 'invalid', 'roles' => []])->assertSessionHasErrors(['is_active', 'roles']);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_seeding_is_idempotent_and_admin_has_no_clinical_bypass(): void
    {
        $permissionCount = Permission::count();
        $this->seed(RolePermissionSeeder::class);
        $this->assertEquals(7, Role::count());
        $this->assertEquals($permissionCount, Permission::count());
        foreach (['diagnoses.create', 'prescriptions.create', 'laboratory.results', 'pharmacy.dispense'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $this->assertFalse($this->staff('System Admin')->can($permission));
        }
    }

    public function test_first_admin_command_uses_private_prompts_and_refuses_duplicate_bootstrap(): void
    {
        $this->artisan('staff:create-admin')->expectsQuestion('Name', 'Fictional Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'fictional-password-123')
            ->expectsQuestion('Confirm password', 'fictional-password-123')->assertSuccessful();
        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('System Admin'));
        $this->artisan('staff:create-admin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('activity_log', ['description' => 'staff.admin_bootstrapped']);
    }

    public function test_deactivation_removes_database_sessions(): void
    {
        $admin = $this->staff('System Admin');
        $target = $this->staff();
        DB::table('sessions')->insert([
            'id' => 'fictional-session', 'user_id' => $target->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => time(),
        ]);
        config(['session.driver' => 'database']);
        $this->actingAs($admin)->patch(route('staff.update', $target), ['is_active' => false, 'roles' => ['Nurse']])->assertRedirect('/staff');
        $this->assertDatabaseMissing('sessions', ['id' => 'fictional-session']);
    }

    public function test_removing_role_permissions_takes_effect_on_next_request(): void
    {
        $user = $this->staff('System Admin');
        $this->actingAs($user)->get('/staff')->assertOk();
        $user->syncRoles(['Nurse']);
        $this->actingAs($user->fresh())->get('/staff')->assertForbidden();
    }

    public function test_bootstrap_rejects_weak_password_without_creating_account(): void
    {
        $this->artisan('staff:create-admin')->expectsQuestion('Name', 'Fictional Admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_full_database_seeder_works_when_model_events_are_disabled(): void
    {
        Role::query()->delete();
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(DatabaseSeeder::class);
        $this->assertEquals(7, Role::count());
        $this->assertTrue(Role::findByName('System Admin')->hasPermissionTo('users.manage'));
    }
}
