<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVisitDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    public function test_admin_can_read_completion_without_clinical_or_write_access(): void
    {
        $admin = User::where('email', 'admin@rhu.test')->firstOrFail();
        $doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
        $visit = Visit::firstOrFail();
        $visit->forceFill(['status' => 'COMPLETED', 'completed_by' => $doctor->id,
            'completed_at' => '2026-09-26 01:30:00',
            'completion_remarks' => "Checkout reviewed.\n<script>alert('test')</script>"])->save();
        $visit->patient->update(['contact_number' => 'PRIVATE-CONTACT']);
        $this->actingAs($admin);
        $url = route('visits.monitor', [$visit->patient, $visit]);
        $this->get(route('visits.index', ['from' => $visit->visit_date->toDateString()]))
            ->assertOk()->assertSee($url)->assertSee('View details');
        $this->get($url)->assertOk()->assertSee('Checkout reviewed.')
            ->assertSee($doctor->name)->assertSee('Sep 26, 2026 9:30:00 AM')
            ->assertSee("<script>alert('test')</script>")
            ->assertDontSee("<script>alert('test')</script>", false)
            ->assertDontSee('PRIVATE-CONTACT')->assertDontSee('Open Digital ITR')
            ->assertDontSee('Review and complete visit');
        $this->get(route('itr.edit', [$visit->patient, $visit]))->assertForbidden();
        $this->post(route('visits.complete', [$visit->patient, $visit]), ['confirm' => 1])->assertForbidden();
    }

    public function test_monitor_permission_scoping_open_and_archived_records(): void
    {
        $visit = Visit::firstOrFail();
        $url = route('visits.monitor', [$visit->patient, $visit]);
        $this->get($url)->assertRedirect(route('login'));
        foreach (['information', 'doctor', 'nurse', 'medtech', 'pharmacist', 'midwife'] as $account) {
            $this->actingAs(User::where('email', $account.'@rhu.test')->firstOrFail());
            $this->get($url)->assertForbidden();
        }
        $admin = User::where('email', 'admin@rhu.test')->firstOrFail();
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('has not been marked completed');
        $other = Visit::where('patient_id', '!=', $visit->patient_id)->firstOrFail();
        $this->get(route('visits.monitor', [$other->patient, $visit]))->assertNotFound();
        $visit->patient->delete();
        $this->get($url)->assertNotFound();
    }
}
