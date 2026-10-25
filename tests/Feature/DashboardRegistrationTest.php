<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_midwife_scroll_sections_keep_their_service_boundaries(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $registrar = User::factory()->create();
        $registrar->assignRole('Information Staff');
        $this->actingAs($registrar)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Scope']);
        $patient = Patient::firstOrFail();
        foreach (['CONSULTATION', 'MIDWIFE_CARE', 'VACCINATION'] as $code) {
            Visit::forceCreate(['patient_id' => $patient->id, 'service_id' => Service::where('seed_key', $code)->firstOrFail()->id, 'created_by' => $registrar->id, 'visit_number' => 'SCOPE-'.$code, 'visit_date' => now('Asia/Manila')->toDateString(), 'status' => 'OPEN']);
        }
        $midwife = User::factory()->create();
        $midwife->assignRole('Midwife');
        $this->actingAs($midwife)->getJson('/dashboard/visits?section=midwife')->assertOk()->assertSee('SCOPE-MIDWIFE_CARE')->assertDontSee('SCOPE-VACCINATION')->assertDontSee('SCOPE-CONSULTATION');
        $this->getJson('/dashboard/visits?section=vaccinations')->assertOk()->assertSee('SCOPE-VACCINATION')->assertDontSee('SCOPE-MIDWIFE_CARE')->assertDontSee('SCOPE-CONSULTATION');
        $this->getJson('/dashboard/visits?section=laboratory')->assertForbidden();
    }

    public function test_dashboard_loads_bounded_cursor_batches_and_preserves_service_scope(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Information Staff');
        $this->actingAs($user)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Queue']);
        $patient = Patient::firstOrFail();
        $service = Service::firstOrFail();
        foreach (range(1, 25) as $number) {
            Visit::forceCreate(['patient_id' => $patient->id, 'service_id' => $service->id, 'created_by' => $user->id, 'visit_number' => 'BATCH-'.$number, 'queue_reference' => 'LOAD-'.$number, 'visit_date' => now('Asia/Manila')->toDateString(), 'status' => 'OPEN']);
        }
        $page = $this->get('/dashboard?service_id='.$service->id)->assertOk();
        $this->assertCount(10, $page->viewData('todayVisits'));
        $this->assertSame(25, $page->viewData('todayVisitsTotal'));
        $next = $this->getJson($page->viewData('nextVisitsUrl'))->assertOk();
        $this->assertSame(10, substr_count($next->json('html'), 'class="border-t'));
        $last = $this->getJson($next->json('next'))->assertOk()->assertJsonPath('next', null);
        $this->assertSame(5, substr_count($last->json('html'), 'class="border-t'));
        $this->assertStringNotContainsString('BATCH-25', $next->json('html'));
        $this->get('/dashboard?service_id='.Service::where('id', '!=', $service->id)->first()->id)->assertViewHas('todayVisitsTotal', 0);
        $this->getJson('/dashboard/visits?search=LOAD-25')->assertOk()->assertSee('BATCH-25')->assertDontSee('BATCH-24');
        foreach (['Nurse' => 'assessments', 'MedTech' => 'laboratory'] as $role => $section) {
            $departmentUser = User::factory()->create();
            $departmentUser->assignRole($role);
            $dashboard = $this->actingAs($departmentUser)->get('/dashboard')->assertOk();
            $this->assertCount(10, $dashboard->viewData('departmentBatches')[$section]);
            $this->assertSame(25, $dashboard->viewData('departmentTotals')[$section]);
            if ($role === 'Nurse') {
                $cards = $dashboard->viewData('summaryCards');
                $this->assertSame(['Visits today', 'Assessed this month', 'Assessed today', 'Not yet assessed', 'Reassessment requests'], array_column($cards, 0));
                $this->assertSame([25, 0, 0, 25, 0], array_column($cards, 1));
            }
            $batch = $this->getJson($dashboard->viewData('departmentNext')[$section])->assertOk();
            $this->getJson($batch->json('next'))->assertOk()->assertJsonPath('next', null)->assertSee('BATCH-1');
            $this->getJson('/dashboard/visits?section=pharmacy')->assertForbidden();
        }
        $this->actingAs($user);
        $patient->delete();
        $this->getJson('/dashboard/visits')->assertOk()->assertDontSee('BATCH-25');
        $nurse = User::factory()->create();
        $nurse->assignRole('Nurse');
        $this->actingAs($nurse)->getJson('/dashboard/visits')->assertForbidden();
    }

    public function test_service_cards_count_today_and_search_does_not_change_totals(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Information Staff');
        $this->actingAs($user)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Findable']);
        $patient = Patient::firstOrFail();
        foreach (['TODAY-01', 'TODAY-02', 'OLDER-01'] as $queue) {
            $this->post(route('patients.visits.store', $patient), [
                'service_id' => Service::first()->id, 'queue_reference' => $queue,
                'visit_date' => now('Asia/Manila')->subDays($queue === 'OLDER-01' ? 1 : 0)->toDateString(),
            ])->assertRedirect();
            $patient->visits()->latest('id')->firstOrFail()->forceFill(['queue_reference' => $queue])->save();
        }
        $this->get('/dashboard')->assertDontSee('Find patient — all registered patients')->assertDontSee('Search patients');
        $result = $this->get('/dashboard?search=TODAY-01')->assertOk();
        $cards = collect($result->viewData('summaryCards'))->keyBy(fn ($card) => $card[0]);
        $this->assertSame(2, $cards[Service::first()->name.' today'][1]);
        $this->assertSame(0, $cards['Vaccination today'][1]);
        $this->assertSame('Other RHU Service today', $cards->last()[0]);
        $this->assertCount(1, $result->viewData('todayVisits'));
        $this->assertSame(2, $result->viewData('summaryCards')[0][1]);
        $this->assertSame(3, $result->viewData('summaryCards')[3][1]);
        $patient->visits()->where('queue_reference', 'OLDER-01')->update(['visit_date' => '2020-01-01']);
        $this->get('/visits?all=1')->assertOk()->assertViewHas('visits', fn ($visits) => $visits->total() === 3)->assertSee('OLDER-01');
        $this->get('/visits?all=1&search=OLDER-01')->assertOk()->assertViewHas('visits', fn ($visits) => $visits->total() === 1);
        $this->get('/visits')->assertOk()->assertDontSee('OLDER-01');
        $this->get('/dashboard?search=OLDER-01')->assertViewHas('todayVisits', fn ($visits) => $visits->isEmpty());
        $this->get('/dashboard?search='.$patient->patient_number)->assertViewHas('todayVisits', fn ($visits) => $visits->count() === 2);
        $patient->delete();
        $this->get('/visits?all=1')->assertViewHas('visits', fn ($visits) => $visits->isEmpty());
        $this->get('/dashboard?search=TODAY-01')
            ->assertViewHas('summaryCards', fn ($cards) => collect($cards)->every(fn ($card) => $card[1] === 0))
            ->assertViewHas('todayVisits', fn ($visits) => $visits->isEmpty());
    }

    public function test_search_does_not_grant_registry_or_other_department_access(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        foreach (['System Admin' => 'todayVisits', 'Nurse' => 'todayAssessments', 'MedTech' => 'todayLaboratory', 'Pharmacist' => 'todayPharmacy', 'Midwife' => 'todayMidwifeCare'] as $role => $list) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $response = $this->actingAs($user)->get('/dashboard?patient_search=fictional&search=fictional')->assertOk();
            $response->assertDontSee('Find patient — all registered patients')->assertDontSee('Consultation today');
            $this->assertNotNull($response->viewData($list));
            if ($role !== 'System Admin') {
                $response->assertViewHas('todayVisits', null);
                $this->get('/visits?all=1')->assertForbidden();
            }
            $this->get('/patients?search=fictional')->assertForbidden();
        }
        $this->assertDatabaseCount('visits', 0);
    }
}
