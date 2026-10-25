<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\WorkflowAlert;
use App\Support\FollowUpReminders;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $nurse;

    private User $doctor;

    private User $medtech;

    private User $pharmacist;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        foreach (['staff' => 'Information Staff', 'nurse' => 'Nurse', 'doctor' => 'Doctor / Medical Officer', 'medtech' => 'MedTech', 'pharmacist' => 'Pharmacist'] as $property => $role) {
            $this->{$property} = User::factory()->create(['is_active' => true]);
            $this->{$property}->assignRole($role);
        }
        $this->actingAs($this->staff)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Alert'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
    }

    private function register(array $extra = []): Visit
    {
        $this->actingAs($this->staff)->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()] + $extra)->assertSessionHasNoErrors();

        return Visit::latest('id')->firstOrFail();
    }

    private function assess(Visit $visit): void
    {
        $visit->refresh();
        $this->actingAs($this->nurse)->put(route('assessments.save', [$this->patient, $visit]), ['lock_version' => $visit->vitalSign?->lock_version ?? 0, 'temperature' => 38, 'assessment_summary' => 'Fictional assessment for alerts.'])->assertSessionHasNoErrors();
    }

    private function alerts(User $user, string $kind): int
    {
        return $user->notifications()->where('type', WorkflowAlert::class)->get()->where('data.kind', $kind)->count();
    }

    public function test_each_workflow_step_notifies_the_next_role_and_never_the_actor(): void
    {
        $visit = $this->register();
        $this->assertSame(1, $this->alerts($this->nurse, 'visit_waiting'));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->assertSame(0, $this->pharmacist->notifications()->count());

        $this->assess($visit);
        $this->assertSame(1, $this->alerts($this->doctor, 'vitals_ready'));
        $this->assess($visit);
        $this->assertSame(1, $this->alerts($this->doctor, 'vitals_ready'), 'Only the first saved assessment announces readiness.');

        $this->actingAs($this->doctor)->post(route('reassessments.store', [$this->patient, $visit]), ['reason' => 'Fictional recheck.', 'assessment_version' => $visit->fresh()->vitalSign->lock_version])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->alerts($this->nurse, 'reassessment_requested'));

        $token = (string) Str::uuid();
        $this->post(route('followups.request', [$this->patient, $visit]), ['test_names' => ['Urinalysis', 'Fecalysis'], 'request_reason' => 'Fictional reason.', 'assessment_version' => $visit->fresh()->vitalSign->lock_version, 'submission_token' => $token, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->alerts($this->medtech, 'lab_ordered'));
        $this->actingAs($this->medtech)->get(route('notifications.index'))->assertSee('New laboratory order for '.$this->patient->full_name.': Urinalysis, Fecalysis');

        $record = LaboratoryRecord::where('order_reference', $token)->where('test_name', 'Urinalysis')->firstOrFail();
        $url = route('laboratory.update', [$this->patient, $visit, $record]);
        $this->put($url, ['test_name' => 'Urinalysis', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1, 'result_rows' => [['measurement' => 'Example', 'value' => 'Fictional']]])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->alerts($this->doctor, 'lab_completed'));
        $this->put($url, ['test_name' => 'Urinalysis', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 2, 'result_rows' => [['measurement' => 'Example', 'value' => 'Corrected']]])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->alerts($this->doctor, 'lab_completed'), 'Corrections do not notify again.');
        $this->actingAs($this->doctor)->get(route('notifications.index'))->assertSee('Urinalysis for '.$this->patient->full_name.': Completed at RHU');

        $this->put(route('itr.save', [$this->patient, $visit]), ['lock_version' => 0, 'vitals_version' => $visit->fresh()->vitalSign->lock_version, 'assessment' => 'Fictional assessment'])->assertSessionHasNoErrors();
        $this->put(route('prescriptions.show', [$this->patient, $visit]), ['lock_version' => 0, 'items' => [['medicine_name' => 'Fictional medicine', 'dosage' => 'Example', 'frequency' => 'Example', 'quantity_prescribed' => '2']]])->assertSessionHasNoErrors();
        $this->post(route('prescriptions.issue', [$this->patient, $visit]), ['lock_version' => 1, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->alerts($this->pharmacist, 'prescription_issued'));
        $this->assertSame(0, $this->doctor->notifications()->get()->where('data.kind', 'prescription_issued')->count());

        $this->assertStringNotContainsString('Fictional', DatabaseNotification::pluck('data')->toJson());
    }

    public function test_emergency_alerts_nurses_and_doctors_once(): void
    {
        $this->register(['priority' => 'EMERGENCY']);
        $this->assertSame(1, $this->alerts($this->nurse, 'emergency'));
        $this->assertSame(1, $this->alerts($this->doctor, 'emergency'));
        $this->assertSame(0, $this->alerts($this->nurse, 'visit_waiting'));
        $this->actingAs($this->doctor)->get(route('notifications.index'))->assertSee('EMERGENCY visit: '.$this->patient->full_name);
        $this->actingAs($this->doctor)->getJson(route('notifications.poll'))->assertOk()->assertJsonPath('latest.0.urgent', true);

        $regular = $this->register();
        $this->actingAs($this->nurse)->put(route('visits.priority', [$this->patient, $regular]), ['priority' => 'EMERGENCY'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->alerts($this->doctor, 'emergency'));
        $this->assertSame(1, $this->alerts($this->nurse, 'emergency'), 'The nurse who escalated is not alerted about their own change.');
        $this->put(route('visits.priority', [$this->patient, $regular]), ['priority' => 'PRIORITY', 'priority_reason' => 'PWD'])->assertSessionHasNoErrors();
        $this->put(route('visits.priority', [$this->patient, $regular]), ['priority' => 'EMERGENCY'])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->alerts($this->doctor, 'emergency'));
    }

    public function test_poll_is_private_and_details_follow_current_access(): void
    {
        $visit = $this->register();
        $notification = $this->nurse->notifications()->sole();
        $this->actingAs($this->nurse)->getJson(route('notifications.poll'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('unread', 1)->assertJsonPath('latest.0.id', $notification->id)
            ->assertJsonPath('latest.0.headline', 'New visit waiting for assessment: '.$this->patient->full_name)
            ->assertJsonPath('latest.0.url', route('notifications.open', $notification->id));
        $this->actingAs($this->pharmacist)->getJson(route('notifications.poll'))->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'latest');
        $this->actingAs($this->pharmacist)->get(route('notifications.open', $notification->id))->assertNotFound();
        $this->actingAs($this->nurse)->get(route('notifications.open', $notification->id))->assertRedirect(route('assessments.edit', [$this->patient, $visit]));
        $this->assertNotNull($notification->fresh()->read_at);

        $this->nurse->removeRole('Nurse');
        $this->nurse->assignRole('Pharmacist');
        $this->actingAs($this->nurse->fresh())->get(route('notifications.index'))->assertOk()->assertSee('New visit waiting for assessment')->assertDontSee($this->patient->full_name);
        $this->get(route('notifications.open', $notification->id))->assertForbidden();
        auth()->logout();
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
    }

    public function test_doctor_schedules_follow_up_and_staff_are_reminded_once_when_due(): void
    {
        $visit = $this->register();
        $url = route('visits.follow-up', [$this->patient, $visit]);
        $this->actingAs($this->doctor)->put($url, ['follow_up_on' => now('Asia/Manila')->subDay()->toDateString(), 'follow_up_reason' => 'x'])->assertSessionHasErrors('follow_up_on');
        $this->put($url, ['follow_up_on' => now('Asia/Manila')->addDays(3)->toDateString()])->assertSessionHasErrors('follow_up_reason');
        $this->put($url, ['follow_up_on' => now('Asia/Manila')->addYears(2)->toDateString(), 'follow_up_reason' => 'x'])->assertSessionHasErrors('follow_up_on');
        $this->put($url, ['follow_up_on' => now('Asia/Manila')->toDateString(), 'follow_up_reason' => 'x', 'follow_up_set_by' => $this->staff->id])->assertSessionHasErrors('follow_up_set_by');
        foreach ([$this->staff, $this->nurse, $this->pharmacist] as $user) {
            $this->actingAs($user)->put($url, ['follow_up_on' => now('Asia/Manila')->toDateString(), 'follow_up_reason' => 'x'])->assertForbidden();
        }
        $this->actingAs($this->staff)->post('/patients', ['birth_date' => '1995-03-03', 'sex' => 'Male', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Other', 'last_name' => 'Fictional'])->assertRedirect();
        $other = Patient::latest('id')->firstOrFail();
        $this->actingAs($this->doctor)->put(route('visits.follow-up', [$other, $visit]), ['follow_up_on' => now('Asia/Manila')->toDateString(), 'follow_up_reason' => 'x'])->assertNotFound();
        $this->assertNull($visit->fresh()->follow_up_on);

        $this->put($url, ['follow_up_on' => now('Asia/Manila')->addDays(3)->toDateString(), 'follow_up_reason' => 'Fictional recheck of fever.'])->assertSessionHasNoErrors();
        $this->assertSame($this->doctor->id, $visit->fresh()->follow_up_set_by);
        $this->assertStringNotContainsString('Fictional recheck', Activity::where('description', 'visit.follow_up_scheduled')->sole()->properties->toJson());
        $this->get(route('consultations.show', [$this->patient, $visit]))->assertSee('Fictional recheck of fever.');
        $this->actingAs($this->staff)->get('/dashboard')->assertSee('Follow-up return visits')->assertSee('Upcoming')->assertSee('Fictional recheck of fever.');
        $this->assertSame(0, FollowUpReminders::dispatchDue(true));

        $this->travel(3)->days();
        $this->assertSame(1, FollowUpReminders::dispatchDue(true));
        $this->assertSame(0, FollowUpReminders::dispatchDue(true));
        $this->assertSame(1, $this->alerts($this->staff, 'followup_due'));
        $this->assertSame(1, $this->alerts($this->doctor, 'followup_due'));
        $this->assertSame(0, $this->alerts($this->nurse, 'followup_due'));
        $this->actingAs($this->staff)->get('/dashboard')->assertSee('Today');
        $this->get(route('notifications.index'))->assertSee('Follow-up visit due');

        $this->register();
        $this->get('/dashboard')->assertDontSee('Follow-up return visits');
        $this->assertSame(0, FollowUpReminders::outstanding()->count());
    }

    public function test_changing_or_clearing_follow_up_resets_the_reminder(): void
    {
        $visit = $this->register();
        $url = route('visits.follow-up', [$this->patient, $visit]);
        $this->actingAs($this->doctor)->put($url, ['follow_up_on' => now('Asia/Manila')->toDateString(), 'follow_up_reason' => 'Fictional same-day recheck.'])->assertSessionHasNoErrors();
        $this->assertSame(1, FollowUpReminders::dispatchDue(true));
        $this->put($url, ['follow_up_on' => now('Asia/Manila')->addDay()->toDateString(), 'follow_up_reason' => 'Moved.'])->assertSessionHasNoErrors();
        $this->assertNull($visit->fresh()->follow_up_notified_at);
        $this->put($url, ['clear' => 1])->assertSessionHasNoErrors();
        $this->assertNull($visit->fresh()->follow_up_on);
        $this->assertNull($visit->fresh()->follow_up_set_by);
        $this->travel(2)->days();
        $this->assertSame(0, FollowUpReminders::dispatchDue(true));
    }

    public function test_polling_has_its_own_rate_limit_bucket(): void
    {
        $this->actingAs($this->staff);
        foreach (range(1, 8) as $attempt) {
            $this->getJson(route('notifications.poll'))->assertOk();
        }
        $this->put(route('password.update'), ['current_password' => 'wrong', 'password' => 'NewFictional123!', 'password_confirmation' => 'NewFictional123!'])
            ->assertSessionHasErrors('current_password');
    }

    public function test_editing_only_the_reason_does_not_resend_a_due_reminder(): void
    {
        $visit = $this->register();
        $url = route('visits.follow-up', [$this->patient, $visit]);
        $today = now('Asia/Manila')->toDateString();
        $this->actingAs($this->doctor)->put($url, ['follow_up_on' => $today, 'follow_up_reason' => 'Fictional reason.'])->assertSessionHasNoErrors();
        $this->assertSame(1, FollowUpReminders::dispatchDue(true));
        $this->put($url, ['follow_up_on' => $today, 'follow_up_reason' => 'Fictional reason, corrected.'])->assertSessionHasNoErrors();
        $this->assertNotNull($visit->fresh()->follow_up_notified_at);
        $this->assertSame(0, FollowUpReminders::dispatchDue(true));
        $this->assertSame(1, $this->alerts($this->staff, 'followup_due'));
    }

    public function test_header_bell_dropdown_lists_own_recent_notifications(): void
    {
        $this->register();
        $this->actingAs($this->nurse)->get('/dashboard')->assertOk()
            ->assertSee('aria-controls="notification-panel"', false)->assertSee('Notifications, 1 unread')
            ->assertDontSee('<span class="sidebar-label">Notifications</span>', false);
        $notification = $this->nurse->notifications()->sole();
        $this->getJson(route('notifications.panel'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('items.0.id', $notification->id)->assertJsonPath('items.0.unread', true)
            ->assertJsonPath('items.0.headline', 'New visit waiting for assessment: '.$this->patient->full_name)
            ->assertJsonPath('items.0.url', route('notifications.open', $notification->id));
        $this->actingAs($this->pharmacist)->getJson(route('notifications.panel'))->assertOk()->assertJsonCount(0, 'items');
    }
}
