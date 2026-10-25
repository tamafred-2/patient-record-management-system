<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VisitPriorityTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->staff = $this->user('Information Staff');
        $this->actingAs($this->staff)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Priority'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function register(array $priority): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()] + $priority);
    }

    private function visit(Patient $patient, string $queue, string $priority = 'REGULAR', string $status = 'OPEN'): Visit
    {
        $visit = new Visit;
        $visit->patient()->associate($patient);
        $visit->service_id = Service::first()->id;
        $visit->visit_date = now('Asia/Manila')->toDateString();
        $visit->queue_reference = $queue;
        $visit->created_by = $this->staff->id;
        $visit->status = $status;
        $visit->applyPriority(['priority' => $priority, 'priority_reason' => $priority === 'PRIORITY' ? 'SENIOR' : null, 'priority_note' => null]);
        $visit->save();

        return $visit;
    }

    public function test_registration_defaults_to_regular_and_records_selected_level(): void
    {
        $this->register([])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['REGULAR', 0, null], [Visit::firstOrFail()->priority, Visit::firstOrFail()->priority_rank, Visit::firstOrFail()->priority_reason]);

        $this->register(['priority' => 'PRIORITY', 'priority_reason' => 'PWD'])->assertSessionHasNoErrors()->assertRedirect();
        $visit = Visit::latest('id')->firstOrFail();
        $this->assertSame(['PRIORITY', 1, 'PWD'], [$visit->priority, $visit->priority_rank, $visit->priority_reason]);
        $this->assertSame('PRIORITY', Activity::where('description', 'visit.created')->latest('id')->firstOrFail()->properties['priority']);
    }

    public function test_registration_validates_reason_note_and_tampered_rank(): void
    {
        $this->register(['priority' => 'PRIORITY'])->assertSessionHasErrors('priority_reason');
        $this->register(['priority' => 'PRIORITY', 'priority_reason' => 'OTHER'])->assertSessionHasErrors('priority_note');
        $this->register(['priority' => 'EMERGENCY', 'priority_reason' => 'PWD'])->assertSessionHasErrors('priority_reason');
        $this->register(['priority' => 'URGENT'])->assertSessionHasErrors('priority');
        $this->register(['priority' => 'REGULAR', 'priority_rank' => 2])->assertSessionHasErrors('priority_rank');
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_allowed_roles_change_priority_with_audit(): void
    {
        $visit = $this->visit($this->patient, 'TEST-1');
        foreach (['Nurse' => ['EMERGENCY', null], 'Doctor / Medical Officer' => ['PRIORITY', 'PREGNANT'], 'Information Staff' => ['REGULAR', null]] as $role => [$level, $reason]) {
            $this->actingAs($this->user($role))->put(route('visits.priority', [$this->patient, $visit]), ['priority' => $level, 'priority_reason' => $reason])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame([$level, Visit::PRIORITY_RANKS[$level], $reason], [$visit->fresh()->priority, $visit->fresh()->priority_rank, $visit->fresh()->priority_reason]);
        }
        $audits = Activity::where('description', 'visit.priority_changed')->get();
        $this->assertCount(3, $audits);
        $this->assertSame(['priority' => 'REGULAR', 'priority_reason' => null], $audits->first()->properties['from']);

        $this->actingAs($this->user('Nurse'))->put(route('visits.priority', [$this->patient, $visit]), ['priority' => 'PRIORITY', 'priority_reason' => 'OTHER', 'priority_note' => 'Fictional mobility concern'])->assertSessionHasNoErrors();
        $this->assertSame('Priority · Fictional mobility concern', $visit->fresh()->priorityLabel());
        $this->assertStringNotContainsString('Fictional mobility concern', Activity::all()->toJson());
    }

    public function test_other_roles_are_denied_and_nothing_changes(): void
    {
        $visit = $this->visit($this->patient, 'TEST-1');
        foreach (['Pharmacist', 'MedTech', 'System Admin', 'Midwife'] as $role) {
            $this->actingAs($this->user($role))->put(route('visits.priority', [$this->patient, $visit]), ['priority' => 'EMERGENCY'])->assertForbidden();
        }
        $this->assertSame('REGULAR', $visit->fresh()->priority);
        $this->assertDatabaseMissing('activity_log', ['description' => 'visit.priority_changed']);
    }

    public function test_cross_patient_and_closed_visits_are_rejected(): void
    {
        $this->actingAs($this->staff)->post('/patients', ['birth_date' => '1990-02-02', 'sex' => 'Male', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Other', 'last_name' => 'Fictional'])->assertRedirect();
        $other = Patient::latest('id')->firstOrFail();
        $otherVisit = $this->visit($other, 'TEST-OTHER');
        $nurse = $this->user('Nurse');
        $this->actingAs($nurse)->put(route('visits.priority', [$this->patient, $otherVisit]), ['priority' => 'EMERGENCY'])->assertNotFound();
        $this->assertSame('REGULAR', $otherVisit->fresh()->priority);

        $closed = $this->visit($this->patient, 'TEST-CLOSED', 'REGULAR', 'COMPLETED');
        $this->actingAs($nurse)->put(route('visits.priority', [$this->patient, $closed]), ['priority' => 'EMERGENCY'])->assertSessionHasErrors('priority');
        $this->assertSame('REGULAR', $closed->fresh()->priority);
    }

    public function test_queues_list_emergency_then_priority_then_regular_across_pages(): void
    {
        $this->visit($this->patient, 'Q-EMERGENCY', 'EMERGENCY');
        foreach (range(1, 10) as $number) {
            $this->visit($this->patient, sprintf('Q-REGULAR-%02d', $number));
        }
        $this->visit($this->patient, 'Q-PRIORITY', 'PRIORITY');
        $this->visit($this->patient, 'Q-LATEST-REGULAR');

        $nurse = $this->user('Nurse');
        $page = $this->actingAs($nurse)->get(route('assessments.index'))->assertOk()
            ->assertSeeInOrder(['Q-EMERGENCY', 'Emergency', 'Q-PRIORITY', 'Priority · Senior citizen', 'Q-LATEST-REGULAR'])
            ->assertDontSee('Q-REGULAR-01');
        $next = $page->viewData('visits')->nextPageUrl();
        $this->get($next)->assertOk()->assertSee('Q-REGULAR-01')->assertDontSee('Q-EMERGENCY');
    }

    public function test_stub_shows_priority_and_clinical_pages_show_inline_badge(): void
    {
        $visit = $this->visit($this->patient, 'TEST-STUB', 'EMERGENCY');
        $this->actingAs($this->staff)->get(route('visits.stub', [$this->patient, $visit]))->assertOk()->assertSee('class="priority emergency"', false)->assertSee('Emergency');
        $regular = $this->visit($this->patient, 'TEST-STUB-2');
        $this->get(route('visits.stub', [$this->patient, $regular]))->assertOk()->assertDontSee('class="priority', false);
        $nurse = $this->user('Nurse');
        $this->actingAs($nurse)->get(route('assessments.edit', [$this->patient, $visit]))->assertOk()
            ->assertSee('Change visit priority')->assertDontSee('id="visit-priority-title"', false);
        $this->actingAs($this->user('Pharmacist'))->get(route('patients.visits.show', [$this->patient, $visit]))->assertForbidden();
        $this->actingAs($this->staff)->get(route('patients.visits.show', [$this->patient, $visit]))->assertSee('id="visit-priority-title"', false);
    }
}
