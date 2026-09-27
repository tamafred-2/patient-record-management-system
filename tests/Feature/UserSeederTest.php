<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_active_role_accounts_that_can_sign_in_and_audits_without_passwords(): void
    {
        $this->seed(UserSeeder::class);
        $this->assertDatabaseCount('users', 7);
        foreach (UserSeeder::ACCOUNTS as $email => $role) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasExactRoles([$role]));
            $this->assertTrue(Hash::check(UserSeeder::PASSWORD, $user->password));
            $this->post('/login', ['email' => $email, 'password' => UserSeeder::PASSWORD])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
        $this->assertFalse(User::where('email', 'admin@rhu.test')->first()->can('patients.create'));
        $this->assertDatabaseCount('activity_log', 7);
        $this->assertStringNotContainsString(UserSeeder::PASSWORD, Activity::all()->toJson());
    }

    public function test_rerunning_preserves_existing_account_password_status_and_roles(): void
    {
        $this->seed(UserSeeder::class);
        $user = User::where('email', 'nurse@rhu.test')->firstOrFail();
        $user->password = 'changed-password-123';
        $user->is_active = false;
        $user->name = 'Modified demo account';
        $user->save();
        $user->syncRoles(['Midwife']);
        $this->seed(UserSeeder::class);
        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('activity_log', 7);
        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('changed-password-123', $user->password));
        $this->assertTrue($user->hasExactRoles(['Midwife']));
        $this->assertSame('Modified demo account', $user->name);
    }

    public function test_production_is_rejected_before_creating_accounts_or_roles(): void
    {
        $original = app()->environment();
        app()->instance('env', 'production');
        try {
            app(UserSeeder::class)->run();
            $this->fail('Production seeding should be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('local or testing', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertSame(0, Role::count());
        } finally {
            app()->instance('env', $original);
        }
    }
}
