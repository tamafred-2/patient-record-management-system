<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_filter_dates_are_rejected_using_manila_today(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-24 16:30:00', 'UTC'));
        $this->seed(WorkflowDemoSeeder::class);
        foreach ([['information', '/visits', 'from'], ['information', '/visits', 'to'], ['nurse', '/assessments', 'date'], ['doctor', '/consultations', 'date'], ['admin', '/eligibility', 'date'], ['medtech', '/laboratory', 'date'], ['pharmacist', '/pharmacy', 'date'], ['midwife', '/midwife-care', 'date'], ['midwife', '/vaccinations', 'date'], ['admin', '/analytics', 'to'], ['admin', '/reports', 'from'], ['admin', '/reports/pdf', 'to'], ['admin', '/audit', 'to']] as [$role, $url, $field]) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->getJson($url.'?'.$field.'=2026-10-26')->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->actingAs(User::where('email', 'information@rhu.test')->firstOrFail());
        $this->get('/visits?from=2026-10-25&to=2026-10-25')->assertOk();
        $this->getJson('/visits?from=2026-10-25&to=2026-10-24')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/visits?from=2024-01-01&to=2026-10-25')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->get('/visits?from=2025-10-25&to=2026-10-25')->assertOk();
        $this->travelBack();
    }
}
