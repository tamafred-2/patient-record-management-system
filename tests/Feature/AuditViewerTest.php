<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('System Admin');
        $this->actingAs($this->admin);
    }

    private function event(string $action, string $time, ?User $actor = null): Activity
    {
        $event = new Activity;
        $event->description = $action;
        $event->log_name = 'test';
        $event->causer_type = $actor?->getMorphClass();
        $event->causer_id = $actor?->id;
        $event->subject_type = Patient::class;
        $event->subject_id = 123;
        $event->properties = ['secret' => 'Fictional confidential content', 'password' => 'Never display this'];
        $event->created_at = CarbonImmutable::parse($time, 'UTC');
        $event->save();

        return $event;
    }

    public function test_admin_filters_by_local_dates_actor_and_action_without_payloads(): void
    {
        $staff = User::factory()->create(['name' => 'Example operator']);
        $match = $this->event('patient.created', '2026-09-23 16:00:00', $staff);
        $this->event('patient.created', '2026-09-23 15:59:59', $staff);
        $this->event('patient.created', '2026-09-24 16:00:00', $staff);
        $this->event('patient.updated', '2026-09-24 04:00:00', $staff);
        $this->event('patient.created', '2026-09-24 04:00:00', $this->admin);
        $response = $this->get('/audit?from=2026-09-24&to=2026-09-24&staff='.$staff->id.'&action=patient.created');
        $response->assertOk()->assertSee('1 matching events')->assertSee('Patient registered')->assertSee('Patient #123')->assertSee('Event #'.$match->id)->assertDontSee('Fictional confidential content')->assertDontSee('Never display this')->assertDontSee($staff->email);
        $this->get('/patients')->assertForbidden();
    }

    public function test_unknown_events_system_and_missing_actor_are_safe(): void
    {
        $this->event('<script>secret clinical text</script>', '2026-09-24 01:00:00');
        $former = $this->event('visit.created', '2026-09-24 02:00:00');
        $former->causer_type = (new User)->getMorphClass();
        $former->causer_id = 999;
        $former->save();
        $this->get('/audit?from=2026-09-24&to=2026-09-24&staff=system')->assertOk()->assertSee('1 matching events')->assertSee('Other recorded activity')->assertDontSee('secret clinical text');
        $this->get('/audit?from=2026-09-24&to=2026-09-24')->assertSee('Former user #999');
    }

    public function test_pagination_default_range_and_input_validation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-24 08:00:00', 'UTC'));
        for ($i = 0; $i < 26; $i++) {
            $this->event('visit.created', '2026-09-24 01:00:00');
        }
        $this->event('visit.created', '2026-09-01 01:00:00');
        $this->get('/audit')->assertOk()->assertSee('26 matching events')->assertViewHas('events', fn ($events) => $events->count() === 25);
        $this->get('/audit?page=2&action=visit.created')->assertOk()->assertViewHas('events', fn ($events) => $events->count() === 1)->assertSee('action=visit.created', false);
        foreach (['from=bad', 'from=2026-09-25&to=2026-09-24', 'staff[]=1', 'action=secret'] as $query) {
            $this->get('/audit?'.$query)->assertSessionHasErrors();
        }
    }

    public function test_denied_roles_guest_and_no_mutation_endpoint(): void
    {
        foreach (['Information Staff', 'Nurse', 'Doctor / Medical Officer', 'MedTech', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get('/audit')->assertForbidden();
        }
        $this->actingAs($this->admin)->delete('/audit')->assertStatus(405);
        auth()->logout();
        $this->get('/audit')->assertRedirect('/login');
    }
}
