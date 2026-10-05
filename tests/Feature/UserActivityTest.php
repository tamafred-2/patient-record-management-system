<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_successful_sign_in_sets_sign_in_and_requests_throttle_activity_writes(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['password' => 'FictionalPassword!']);
        $user->assignRole('Nurse');
        $this->freezeTime();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors();
        $this->assertNull($user->fresh()->last_sign_in_at);
        $this->assertNull($user->fresh()->last_active_at);
        $this->post('/login', ['email' => $user->email, 'password' => 'FictionalPassword!'])->assertRedirect('/dashboard');
        $signedIn = $user->fresh()->last_sign_in_at;
        $this->assertTrue($user->fresh()->recently_active);
        $this->travel(30)->seconds();
        $this->get('/dashboard')->assertOk();
        $this->assertTrue($signedIn->eq($user->fresh()->last_active_at));
        $this->travel(40)->seconds();
        $this->get('/dashboard')->assertOk();
        $active = $user->fresh()->last_active_at;
        $this->assertTrue($active->gt($signedIn));
        $this->assertTrue($signedIn->eq($user->fresh()->last_sign_in_at));
        $this->travel(5)->minutes();
        $this->assertFalse($user->fresh()->recently_active);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertTrue($active->eq($user->fresh()->last_active_at));
        $user->forceFill(['is_active' => false])->save();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertTrue($active->eq($user->fresh()->last_active_at));
    }

    public function test_user_activity_is_admin_only_and_separate_from_account_status(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $nurse = User::factory()->create(['last_active_at' => now(), 'is_active' => false]);
        $nurse->assignRole('Nurse');
        $this->assertFalse($nurse->recently_active);
        $this->actingAs($admin)->get('/staff')->assertOk()->assertSee('Recently active')->assertSee('Last active')->assertDontSee('Last sign-in')->assertSee('Offline')->assertSee('Deactivated');
        $nurse->forceFill(['is_active' => true])->save();
        $this->actingAs($nurse)->get('/staff')->assertForbidden();
    }
}
