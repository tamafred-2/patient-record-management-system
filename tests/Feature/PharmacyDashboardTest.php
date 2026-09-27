<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_only_shows_authorized_issued_prescriptions_for_today(): void
    {
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $visit = Visit::firstOrFail();
        $visit->forceFill(['queue_reference' => 'TEST-PHARM-042', 'visit_date' => now('Asia/Manila')->toDateString()])->save();
        $this->actingAs(User::where('email', 'pharmacist@rhu.test')->firstOrFail());
        $this->get('/dashboard')->assertOk()->assertSee("Today's pharmacy", false)
            ->assertSee('DEMO-RX-1')->assertSee('TEST-PHARM-042')
            ->assertSee(route('pharmacy.show', [$visit->patient, $visit]), false)
            ->assertDontSee('Patient name or queue number');
        $visit->visit_date = now('Asia/Manila')->subDay()->toDateString();
        $visit->save();
        $this->get('/dashboard')->assertDontSee('DEMO-RX-1')->assertSee('No issued prescriptions');
        $visit->visit_date = now('Asia/Manila')->toDateString();
        $visit->save();
        $rx = $visit->prescription;
        $rx->status = 'DRAFT';
        $rx->save();
        $this->get('/dashboard')->assertDontSee('DEMO-RX-1');
        $rx->status = 'ISSUED';
        $rx->save();
        foreach (['admin', 'doctor', 'information', 'nurse', 'medtech', 'midwife'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->get('/dashboard')->assertOk()->assertDontSee('today-pharmacy-title')->assertDontSee('DEMO-RX-1');
        }
        $this->actingAs(User::where('email', 'pharmacist@rhu.test')->firstOrFail());
        $visit->patient->delete();
        $this->get('/dashboard')->assertDontSee('DEMO-RX-1');
    }
}
