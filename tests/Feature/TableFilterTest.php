<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    private function login(string $account): User
    {
        $user = User::where('email', $account.'@rhu.test')->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    public function test_user_filters_search_all_batches_preserve_cursor_filters_and_escape_wildcards(): void
    {
        $this->login('admin');
        foreach (range(1, 12) as $index) {
            User::factory()->create(['name' => 'Filter Nurse '.str_pad($index, 2, '0', STR_PAD_LEFT), 'is_active' => true, 'last_active_at' => null])->assignRole('Nurse');
        }
        $url = route('staff.index', ['search' => 'filter nurse', 'role' => 'Nurse', 'status' => 'active', 'activity' => 'offline']);
        $response = $this->get($url)->assertOk();
        $users = $response->viewData('users');
        $this->assertCount(10, $users);
        $this->assertStringContainsString('search=filter%20nurse', $users->nextPageUrl());
        $next = $this->get($users->nextPageUrl())->assertOk()->viewData('users');
        $this->assertCount(2, $next);
        $this->get(route('staff.index', ['search' => '%']))->assertOk()->assertViewHas('users', fn ($users) => $users->isEmpty());
        $this->get(route('staff.index', ['status' => 'invalid']))->assertSessionHasErrors('status');
        $this->login('nurse');
        $this->get($url)->assertForbidden();
    }

    public function test_service_filters_combine_text_and_status(): void
    {
        $this->login('admin');
        Service::create(['name' => 'Fictional Filter Service', 'code' => 'FILTER', 'is_active' => false]);
        $this->get(route('services.index', ['search' => 'filter', 'status' => 'inactive']))->assertOk()
            ->assertViewHas('services', fn ($services) => $services->count() === 1 && $services->first()->code === 'FILTER');
        $this->get(route('services.index', ['search' => 'filter', 'status' => 'active']))->assertViewHas('services', fn ($services) => $services->isEmpty());
    }

    public function test_nurse_filters_match_authorization_state_and_tests_without_broadening_access(): void
    {
        $this->login('doctor');
        $nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
        $this->put(route('nurse-authorizations.store', $nurse), ['test_scope' => 'SELECTED', 'allowed_tests' => ['Urinalysis']])->assertSessionHasNoErrors();
        $this->get(route('nurse-authorizations.index', ['search' => 'urinalysis', 'ordering' => 'on']))->assertOk()
            ->assertViewHas('nurses', fn ($nurses) => $nurses->contains('id', $nurse->id));
        $this->get(route('nurse-authorizations.index', ['search' => $nurse->email, 'ordering' => 'off']))->assertOk()
            ->assertViewHas('nurses', fn ($nurses) => $nurses->isEmpty());
        $this->get(route('nurse-authorizations.index', ['ordering' => 'invalid']))->assertSessionHasErrors('ordering');
        $this->login('nurse');
        $this->get(route('nurse-authorizations.index', ['search' => 'urinalysis']))->assertForbidden();
    }

    public function test_patient_visit_filters_stay_scoped_to_the_patient(): void
    {
        $this->login('information');
        $visit = Visit::where('visit_number', 'DEMO-VISIT-2')->firstOrFail();
        $other = Visit::where('patient_id', '!=', $visit->patient_id)->firstOrFail();
        foreach (['patients.show', 'history.show'] as $route) {
            $this->get(route($route, [$visit->patient, 'search' => $visit->visit_number, 'status' => $visit->status, 'priority' => $visit->priority]))->assertOk()
                ->assertViewHas('visits', fn ($visits) => $visits->count() === 1 && $visits->first()->is($visit));
            $this->get(route($route, [$visit->patient, 'search' => $other->visit_number]))->assertOk()
                ->assertViewHas('visits', fn ($visits) => $visits->isEmpty());
        }
    }
}
