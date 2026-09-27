<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private const OLD = 'OriginalPassword123!';

    private const TEMP = 'TemporaryPassword123!';

    private const NEW = 'PrivateReplacement123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['password' => self::OLD]);
        $this->admin->assignRole('System Admin');
        $this->staff = User::factory()->create(['password' => self::OLD]);
        $this->staff->assignRole('Nurse');
    }

    private function resetData(): array
    {
        return ['current_password' => self::OLD, 'password' => self::TEMP, 'password_confirmation' => self::TEMP, 'confirm' => 1, 'password_version' => 0];
    }

    private function url(): string
    {
        return route('staff.password.reset', $this->staff);
    }

    public function test_admin_reset_forced_change_old_credentials_and_session_versions(): void
    {
        $this->actingAs($this->admin)->post($this->url(), $this->resetData())->assertRedirect(route('staff.edit', $this->staff));
        $staff = $this->staff->fresh();
        $this->assertTrue($staff->must_change_password);
        $this->assertTrue(Hash::check(self::TEMP, $staff->password));
        $this->assertFalse(Hash::check(self::OLD, $staff->password));
        $this->assertTrue($staff->hasRole('Nurse'));
        $this->actingAs($staff)->withSession(['auth_password_version' => 0])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $staff->email, 'password' => self::OLD])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $staff->email, 'password' => self::TEMP])->assertRedirect('/password/change');
        $this->get('/dashboard')->assertRedirect('/password/change');
        $this->get('/analytics?overview=1')->assertRedirect('/password/change');
        $this->post('/patients', [])->assertRedirect('/password/change');
        $this->get('/password/change')->assertOk();
        $this->put('/password/change', ['current_password' => self::TEMP, 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertRedirect('/dashboard');
        $this->assertFalse($staff->fresh()->must_change_password);
        $this->assertSame(2, $staff->fresh()->password_version);
        $this->get('/dashboard')->assertOk();
        $this->withSession(['auth_password_version' => 1])->get('/analytics')->assertRedirect('/login');
        $this->post('/login', ['email' => $staff->email, 'password' => self::TEMP])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $staff->email, 'password' => self::NEW])->assertRedirect('/dashboard');
        $audits = Activity::whereIn('description', ['staff.password_reset', 'staff.password_changed'])->get();
        $this->assertCount(2, $audits);
        foreach ([self::TEMP, self::NEW, self::OLD, $staff->fresh()->password] as $secret) {
            $this->assertStringNotContainsString($secret, $audits->toJson());
        }
    }

    public function test_reset_authorization_confirmation_reauthentication_and_stale_forms(): void
    {
        $this->post($this->url(), $this->resetData())->assertRedirect('/login');
        $this->actingAs($this->staff)->post($this->url(), $this->resetData())->assertForbidden();
        $this->actingAs($this->admin)->post(route('staff.password.reset', $this->admin), $this->resetData())->assertForbidden();
        $data = $this->resetData();
        $this->post($this->url(), array_replace($data, ['current_password' => 'incorrect']))->assertSessionHasErrors('current_password');
        $this->assertNull(session()->getOldInput('current_password'));
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->post($this->url(), array_replace($data, ['confirm' => 0]))->assertSessionHasErrors('confirm');
        $this->post($this->url(), array_replace($data, ['password_version' => 99]))->assertSessionHasErrors('password_version');
        $this->assertTrue(Hash::check(self::OLD, $this->staff->fresh()->password));
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_change_validation_prevents_reuse_short_mismatched_and_multibyte_passwords(): void
    {
        $this->actingAs($this->staff);
        foreach (['short', 'PasswordWith'.chr(0).'Null', self::OLD, str_repeat("\u{00E9}", 40)] as $password) {
            $this->put('/password/change', ['current_password' => self::OLD, 'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        }
        $this->put('/password/change', ['current_password' => 'wrong', 'password' => self::NEW, 'password_confirmation' => self::NEW])->assertSessionHasErrors('current_password');
        $this->put('/password/change', ['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => 'mismatch'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check(self::OLD, $this->staff->fresh()->password));
    }

    public function test_reset_deletes_database_sessions_and_preserves_inactive_status(): void
    {
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'old-staff-session', 'user_id' => $this->staff->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        $this->staff->is_active = false;
        $this->staff->save();
        $this->actingAs($this->admin)->post($this->url(), $this->resetData())->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-staff-session']);
        $this->assertFalse($this->staff->fresh()->is_active);
        $this->assertTrue($this->staff->fresh()->must_change_password);
    }

    public function test_new_staff_requires_change_but_existing_staff_does_not_and_logout_is_allowed(): void
    {
        $this->actingAs($this->admin)->post('/staff', ['name' => 'Fictional new staff', 'email' => 'new-password-test@rhu.test', 'password' => self::TEMP, 'password_confirmation' => self::TEMP, 'roles' => ['Nurse']])->assertRedirect();
        $created = User::where('email', 'new-password-test@rhu.test')->firstOrFail();
        $this->assertTrue($created->must_change_password);
        $this->assertFalse($this->staff->fresh()->must_change_password);
        $this->actingAs($created)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
