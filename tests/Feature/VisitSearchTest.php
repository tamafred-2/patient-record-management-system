<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_and_admin_search_respects_dates_archives_and_access(): void
    {
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $visit = Visit::firstOrFail();
        $visit->forceFill(['visit_date' => now('Asia/Manila')->toDateString(), 'queue_reference' => 'SEARCH-042'])->save();
        $visit->patient->forceFill(['first_name' => 'Fictional Search', 'last_name' => 'Example'])->save();

        foreach (['doctor' => 'consultations.index', 'admin' => 'eligibility.index'] as $role => $route) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            foreach (['search example', 'search-042'] as $search) {
                $this->get(route($route, ['search' => $search]))->assertOk()->assertSee($visit->visit_number);
            }
            $this->get(route($route, ['search' => 'missing-person']))->assertDontSee($visit->visit_number);
            $this->get(route($route, ['search' => '%']))->assertDontSee($visit->visit_number);
            $this->get(route($route, ['search' => 'SEARCH-042', 'date' => '2000-01-01']))->assertDontSee($visit->visit_number);
            $this->get(route($route, ['search' => str_repeat('x', 101)]))->assertSessionHasErrors('search');
            $this->get(route('dashboard', ['search' => 'missing-person']))->assertOk()->assertSee($visit->visit_number)->assertDontSee('Patient name or queue number');
            $this->get(route('dashboard'))
                ->assertSee($role === 'doctor' ? 'Review visit' : 'Open confirmation');
            if ($role === 'admin') {
                $this->get(route('consultations.index', ['search' => 'SEARCH-042']))->assertForbidden();
                $this->get(route('visits.index', ['search' => 'SEARCH-042']))->assertOk()->assertSee($visit->visit_number);
                $this->get(route('dashboard'))->assertDontSee('Review visit');
            }
        }

        $visit->patient->delete();
        $this->get(route('eligibility.index', ['search' => 'SEARCH-042']))->assertDontSee($visit->visit_number);
        $this->get(route('dashboard', ['search' => 'SEARCH-042']))->assertDontSee($visit->visit_number);
        $this->actingAs(User::where('email', 'doctor@rhu.test')->firstOrFail());
        $this->get(route('consultations.index', ['search' => 'SEARCH-042']))->assertDontSee($visit->visit_number);
        $this->get(route('dashboard', ['search' => 'SEARCH-042']))->assertDontSee($visit->visit_number);
        $this->actingAs(User::where('email', 'pharmacist@rhu.test')->firstOrFail());
        $this->get(route('dashboard'))->assertDontSee('Patient name or queue number');
    }
}
