<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->staff = User::factory()->create();
        $this->staff->assignRole('Information Staff');
        $this->actingAs($this->staff)->post('/patients', ['first_name' => 'Fictional', 'last_name' => 'Visit', 'birth_date' => '2000-01-01'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
    }

    private function data(array $extra = []): array
    {
        return array_replace(['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString(), 'queue_reference' => 'CO-027'], $extra);
    }

    private function createVisit(array $extra = []): Visit
    {
        $this->post(route('patients.visits.store', $this->patient), $this->data($extra))->assertRedirect();

        return Visit::latest('id')->firstOrFail();
    }

    public function test_staff_can_create_and_view_visit_in_today_and_patient_history(): void
    {
        $this->get(route('patients.visits.create', $this->patient))->assertOk()->assertSee('Consultation');
        $visit = $this->createVisit();
        $this->assertSame($this->patient->id, $visit->patient_id);
        $this->assertSame($this->staff->id, $visit->created_by);
        $this->assertSame('OPEN', $visit->status);
        $this->assertSame('CO-027', $visit->queue_reference);
        $this->assertMatchesRegularExpression('/^V-\d{8}-\d{4,}$/', $visit->visit_number);
        $this->get(route('patients.visits.show', [$this->patient, $visit]))->assertOk()->assertSee($visit->visit_number);
        $this->get('/visits')->assertOk()->assertSee($visit->visit_number);
        $this->get(route('patients.show', $this->patient))->assertOk()->assertSee($visit->visit_number);
        $second = $this->createVisit(['queue_reference' => 'CO-028']);
        $this->assertNotSame($visit->visit_number, $second->visit_number);
        $this->assertSame('CO-028', $second->queue_reference);
        $audit = Activity::where('description', 'visit.created')->firstOrFail();
        $this->assertEquals($this->staff->id, $audit->causer_id);
        $this->assertEquals($visit->id, $audit->subject_id);
        $this->assertStringNotContainsString('CO-027', $audit->toJson());
    }

    public function test_queue_reference_is_required_and_preserves_external_format(): void
    {
        foreach ([null, '', '   ', str_repeat('X', 101)] as $value) {
            $this->post(route('patients.visits.store', $this->patient), $this->data(['queue_reference' => $value]))->assertSessionHasErrors('queue_reference');
        }
        $data = $this->data();
        unset($data['queue_reference']);
        $this->post(route('patients.visits.store', $this->patient), $data)->assertSessionHasErrors('queue_reference');
        $this->assertDatabaseCount('visits', 0);
        $visit = $this->createVisit(['queue_reference' => '  Q / 001-A  ']);
        $this->assertSame('Q / 001-A', $visit->queue_reference);
    }

    public function test_today_uses_manila_date_and_history_includes_earlier_visits(): void
    {
        $this->travelTo(Carbon::parse('2026-09-24 18:00:00', 'UTC'));
        $today = $this->createVisit();
        $old = $this->createVisit(['visit_date' => '2026-09-24']);
        $this->get('/visits')->assertSee($today->visit_number)->assertDontSee($old->visit_number);
        $this->get('/visits?from='.$old->visit_date->toDateString().'&to='.$old->visit_date->toDateString())->assertOk()->assertSee($old->visit_number)->assertDontSee($today->visit_number);
        $this->get('/visits?from=2026-02-31')->assertSessionHasErrors('from');
        $this->get('/visits?from=2026-02-02&to=2026-01-01')->assertSessionHasErrors('to');
        $this->get(route('patients.show', $this->patient))->assertSee($old->visit_number)->assertSee($today->visit_number);
    }

    public function test_cross_patient_nested_visit_and_submitted_ownership_are_rejected(): void
    {
        $visit = $this->createVisit();
        $this->post('/patients', ['first_name' => 'Another', 'last_name' => 'Fictional'])->assertRedirect();
        $other = Patient::latest('id')->first();
        $this->get(route('patients.visits.show', [$other, $visit]))->assertNotFound()->assertDontSee('CO-027');
        $this->post(route('patients.visits.store', $other), $this->data(['patient_id' => $this->patient->id, 'status' => 'COMPLETED', 'created_by' => 999, 'visit_number' => 'tampered']))->assertSessionHasErrors(['patient_id', 'status', 'created_by', 'visit_number']);
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_invalid_dates_and_inactive_services_cannot_create_visits(): void
    {
        foreach (['1999-12-31', '2026-02-31', now('Asia/Manila')->addDay()->toDateString()] as $date) {
            $this->post(route('patients.visits.store', $this->patient), $this->data(['visit_date' => $date]))->assertSessionHasErrors('visit_date');
        }
        $inactiveService = Service::firstOrFail();
        $inactiveService->update(['is_active' => false]);
        $this->post(route('patients.visits.store', $this->patient), $this->data(['service_id' => $inactiveService->id]))->assertSessionHasErrors('service_id');
        $this->post(route('patients.visits.store', $this->patient), $this->data(['service_id' => 9999]))->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_unauthorized_and_inactive_users_are_denied(): void
    {
        $visit = $this->createVisit();
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin)->get('/visits')->assertOk()->assertSee($visit->visit_number)->assertDontSee(route('patients.visits.show', [$this->patient, $visit]));
        $this->get(route('patients.visits.create', $this->patient))->assertForbidden();
        $this->post(route('patients.visits.store', $this->patient), $this->data())->assertForbidden();
        $this->get(route('patients.visits.show', [$this->patient, $visit]))->assertForbidden();
        $this->staff->is_active = false;
        $this->staff->save();
        $this->actingAs($this->staff)->post(route('patients.visits.store', $this->patient), $this->data())->assertRedirect('/login');
        $this->assertDatabaseCount('visits', 1);
    }

    public function test_archived_patient_is_not_exposed_or_given_new_visits(): void
    {
        $visit = $this->createVisit();
        $this->patient->delete();
        $this->get('/visits')->assertDontSee($visit->visit_number);
        $this->get(route('patients.visits.show', [$this->patient, $visit]))->assertNotFound();
        $this->post(route('patients.visits.store', $this->patient), $this->data())->assertNotFound();
    }

    public function test_admin_manages_services_without_changing_historical_relationships(): void
    {
        $visit = $this->createVisit();
        $service = $visit->service;
        $this->get('/services')->assertForbidden();
        $this->post('/services', ['name' => 'Custom service', 'code' => 'CUSTOM'])->assertForbidden();
        $this->patch(route('services.update', $service), ['name' => 'Changed', 'is_active' => false])->assertForbidden();
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin)->get('/services')->assertOk();
        $this->from('/services')->delete(route('services.destroy', $service))->assertSessionHasErrors('delete');
        $this->assertNotNull(Service::find($service->id));
        $this->post('/services', ['name' => 'Custom service', 'code' => 'CUSTOM'])->assertRedirect();
        $this->patch(route('services.update', $service), ['name' => 'Renamed service', 'code' => 'RENAMED_SERVICE', 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->seed(ServiceSeeder::class);
        $this->assertFalse($service->fresh()->is_active);
        $this->assertSame('Renamed service', $service->fresh()->name);
        $this->assertSame($service->id, $visit->fresh()->service_id);
        $this->actingAs($this->staff)->get(route('patients.visits.show', [$this->patient, $visit]))->assertOk()->assertSee('Renamed service');
        $this->assertDatabaseHas('activity_log', ['description' => 'service.updated']);
    }
}
