<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserAccessFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_denied_browser_page_returns_to_authorized_dashboard_with_warning(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Nurse');
        $this->actingAs($user)->get('/patients', ['Accept' => 'text/html'])
            ->assertRedirect('/dashboard')->assertSessionHas('access_warning');
        $this->get('/dashboard')->assertOk()->assertSee('You do not have permission to open that page.');
        $this->get('/dashboard')->assertOk()->assertDontSee('You do not have permission to open that page.');
    }

    public function test_background_requests_and_writes_remain_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Nurse');
        $this->actingAs($user)->getJson('/patients')->assertForbidden();
        $this->get('/patients', ['Accept' => 'text/html', 'X-Scroll-Queue' => '1'])->assertForbidden();
        $this->post('/patients', [], ['Accept' => 'text/html'])->assertForbidden();
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_account_without_dashboard_access_does_not_redirect_in_a_loop(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard', ['Accept' => 'text/html'])->assertForbidden();
    }

    public function test_invalid_credentials_show_simple_inline_feedback_without_modal(): void
    {
        $this->from('/login')->post('/login', ['email' => 'fictional@example.test', 'password' => 'incorrect'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Invalid email or password.']);
        $this->get('/login')->assertOk()->assertSee('Invalid email or password.')
            ->assertDontSee('id="validation-modal"', false);
        $this->assertGuest();
    }
}
