<?php

namespace Tests\Feature;

use App\Models\FollowupAuthorization;
use App\Models\LaboratoryRecord;
use App\Models\NurseAuthorization;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\NurseOrderingChanged;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FollowupTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    private User $doctor;

    private User $nurse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->visit = Visit::where('visit_number', 'DEMO-VISIT-2')->firstOrFail();
        $this->doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
        $this->nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
        $this->assess($this->visit);
    }

    private function assess(Visit $visit, ?User $nurse = null): void
    {
        $visit->refresh();
        $this->actingAs($nurse ?? $this->nurse)->put(route('assessments.save', [$visit->patient, $visit]), [
            'lock_version' => $visit->vitalSign?->lock_version ?? 0, 'temperature' => 37, 'assessment_summary' => 'Fictional observations for a follow-up workflow test.',
        ])->assertSessionHasNoErrors();
    }

    // Open visits whose saved assessment the demo nurse recorded or last edited, across all patients.
    private function assessedOpenVisits(): int
    {
        return Visit::where('status', 'OPEN')->whereHas('patient')->whereHas('vitalSign', fn ($vital) => $vital->where('recorded_by', $this->nurse->id)->orWhere('updated_by', $this->nurse->id))->count();
    }

    private function url(string $action, mixed $extra = null, ?Visit $visit = null): string
    {
        $visit ??= $this->visit;

        return route('followups.'.$action, array_filter([$visit->patient, $visit, $extra]));
    }

    // Turns on (or changes) standing laboratory ordering for a nurse through the doctor's Nurse authorization page.
    private function enable(array $scope = ['test_scope' => 'SELECTED', 'allowed_tests' => ['Urinalysis']], ?User $doctor = null, ?User $nurse = null): NurseAuthorization
    {
        $nurse ??= $this->nurse;
        $this->actingAs($doctor ?? $this->doctor)->put(route('nurse-authorizations.store', $nurse), $scope + ['instructions' => 'Fictional standing instructions.'])
            ->assertSessionHasNoErrors()->assertRedirect(route('nurse-authorizations.show', $nurse));

        return NurseAuthorization::active()->where('nurse_id', $nurse->id)->sole();
    }

    // Per-visit grants can no longer be created in the UI; older rows must keep working.
    private function legacyGrant(array $tests = ['Urinalysis']): FollowupAuthorization
    {
        $grant = new FollowupAuthorization;
        $grant->visit_id = $this->visit->id;
        $grant->doctor_id = $this->doctor->id;
        $grant->nurse_id = $this->nurse->id;
        $grant->test_name = count($tests) === 1 ? $tests[0] : null;
        $grant->allowed_tests = $tests;
        $grant->instructions = 'Fictional pre-upgrade instructions.';
        $grant->save();

        return $grant->fresh();
    }

    private function payload(?FollowupAuthorization $grant = null, array $tests = ['Urinalysis'], ?Visit $visit = null): array
    {
        return array_merge([
            'request_reason' => 'Fictional follow-up findings for testing.', 'assessment_version' => ($visit ?? $this->visit)->fresh()->vitalSign->lock_version,
            'submission_token' => (string) Str::uuid(), 'confirm' => 1, 'test_names' => $tests,
        ], $grant ? ['authorization_id' => $grant->id] : []);
    }

    public function test_doctor_turns_on_nurse_from_list_and_nurse_orders_with_immutable_provenance(): void
    {
        $this->actingAs($this->doctor)->get(route('nurse-authorizations.index'))->assertOk()->assertSee($this->nurse->name)
            ->assertSee(route('nurse-authorizations.show', $this->nurse), false)->assertSee('Manage');
        $this->get(route('nurse-authorizations.show', $this->nurse))->assertOk()->assertSee('aria-checked="false"', false);
        $this->get($this->url('show'))->assertSee('Nurse assessment')->assertSee('37.00 °C')->assertSee('Fictional observations for a follow-up workflow test.')
            ->assertSee('Recorded by '.$this->nurse->name)->assertDontSee('Nurse ordering')->assertDontSee('Manage nurse authorization');
        $authority = $this->enable();
        $this->get(route('nurse-authorizations.index'))->assertSeeInOrder([$this->nurse->name, 'On', 'Urinalysis', $this->doctor->name]);
        $this->get(route('nurse-authorizations.show', $this->nurse))->assertSee('aria-checked="true"', false)->assertSee('Permitted tests:')->assertSee('Change history');

        $this->actingAs($this->nurse)->get('/assessments?authorized=1')->assertOk()->assertSee($this->visit->visit_number)->assertViewHas('visits', fn ($visits) => $visits->total() === $this->assessedOpenVisits())->assertSee('Order follow-up tests')
            ->assertDontSee('Your authorized follow-up visits:')->assertDontSee('Pending reassessment requests:');
        $this->get($this->url('show'))->assertOk()->assertSee('Authorized by '.$this->doctor->name)->assertSee('Fictional standing instructions.');
        $this->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        $entry = LaboratoryRecord::where('nurse_authorization_id', $authority->id)->sole();
        $this->assertSame([$this->nurse->id, $this->doctor->id, 'PENDING', null], [$entry->created_by, $entry->authorizing_doctor_id, $entry->availability_status, $entry->followup_authorization_id]);
        $this->assertNull($authority->fresh()->revoked_at);
        $this->assertSame('Consultation', $this->visit->service->name);
        $this->actingAs($this->doctor)->get(route('visits.completion', [$this->visit->patient, $this->visit]))->assertViewHas('review', fn ($review) => $review['blocked']);

        $medtech = User::where('email', 'medtech@rhu.test')->firstOrFail();
        $this->actingAs($medtech)->get(route('laboratory.show', [$this->visit->patient, $this->visit]))->assertOk()
            ->assertSee($this->doctor->name)->assertSee($this->nurse->name)->assertSee($entry->request_reason)->assertSee('Followed up by nurse:')->assertSee('Fictional standing instructions.');
        $labUrl = route('laboratory.update', [$this->visit->patient, $this->visit, $entry]);
        $this->put($labUrl, ['test_name' => 'Creatinine', 'availability_status' => 'PENDING', 'lock_version' => 1])->assertSessionHasErrors('test_name');
        $this->put($labUrl, ['test_name' => 'Urinalysis', 'availability_status' => 'PENDING', 'lock_version' => 1, 'authorizing_doctor_id' => $medtech->id])->assertSessionHasErrors('authorizing_doctor_id');
        $this->put($labUrl, ['test_name' => 'Urinalysis', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1, 'result_rows' => [['measurement' => 'Example', 'value' => 'Fictional result']]])->assertSessionHasNoErrors();
        $this->assertSame([$this->doctor->id, $this->nurse->id, 'PERFORMED_RHU'], [$entry->fresh()->authorizing_doctor_id, $entry->fresh()->created_by, $entry->fresh()->availability_status]);
        $this->actingAs($this->nurse)->get(route('laboratory.edit', [$this->visit->patient, $this->visit, $entry]))->assertForbidden();
        $this->assertStringNotContainsString('Fictional', Activity::where('log_name', 'followups')->get()->pluck('properties')->toJson());
    }

    public function test_authorization_applies_to_every_patient_the_nurse_assessed_until_turned_off(): void
    {
        $authority = $this->enable(['test_scope' => 'SELECTED', 'allowed_tests' => ['Urinalysis', 'Complete blood count (CBC)']]);
        $this->travel(2)->years();
        $this->assertTrue($authority->fresh()->usable(), 'Standing authority has no automatic expiry.');
        $other = Visit::where('patient_id', '!=', $this->visit->patient_id)->firstOrFail();
        $other->status = 'OPEN';
        $other->save();
        $this->assess($other);
        $this->assertGreaterThanOrEqual(2, $this->assessedOpenVisits());
        $this->actingAs($this->nurse)->get('/dashboard')->assertOk()->assertDontSee('You can order laboratory tests')->assertDontSee('Open authorized visits');
        $this->post($this->url('request'), $this->payload(null, ['Urinalysis']))->assertSessionHasNoErrors();
        $this->post($this->url('request', null, $other), $this->payload(null, ['Complete blood count (CBC)'], $other))->assertSessionHasNoErrors();
        $this->post($this->url('request'), $this->payload(null, ['Complete blood count (CBC)']))->assertSessionHasNoErrors();
        $this->assertSame(3, LaboratoryRecord::where('nurse_authorization_id', $authority->id)->count());
        $this->actingAs($this->doctor)->delete(route('nurse-authorizations.destroy', $this->nurse))->assertSessionHasNoErrors()->assertSessionHas('status', 'Laboratory ordering turned off for '.$this->nurse->name.'. They have been notified.');
        $this->assertNotNull($authority->fresh()->revoked_at);
        $this->assertSame($this->doctor->id, $authority->fresh()->revoked_by);
        $this->actingAs($this->nurse)->get($this->url('show'))->assertSee('You are not currently authorized to order laboratory tests.');
        $this->post($this->url('request', null, $other), $this->payload(null, ['Urinalysis'], $other))->assertSessionHasErrors('followup');
        $this->assertSame(3, LaboratoryRecord::where('nurse_authorization_id', $authority->id)->count());
        $this->get('/assessments?authorized=1')->assertViewHas('visits', fn ($visits) => $visits->isEmpty());
    }

    public function test_doctor_can_request_directly_and_repeat_submission_is_rejected(): void
    {
        $payload = $this->payload();
        $this->actingAs($this->doctor)->post($this->url('request'), $payload)->assertSessionHasNoErrors();
        $entry = LaboratoryRecord::where('submission_token', $payload['submission_token'])->firstOrFail();
        $this->assertSame($this->doctor->id, $entry->created_by);
        $this->assertNull($entry->followup_authorization_id);
        $this->assertNull($entry->nurse_authorization_id);
        $this->post($this->url('request'), $payload)->assertSessionHasErrors('followup');
        $this->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->get($this->url('show'))->assertOk()->assertSee('Requested by doctor:');
    }

    public function test_unauthorized_roles_and_wrong_nurses_or_patients_cannot_act(): void
    {
        $authority = $this->enable();
        foreach (['admin', 'information', 'medtech', 'pharmacist'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->get($this->url('show'))->assertForbidden();
            $this->post($this->url('request'), $this->payload())->assertForbidden();
            $this->get(route('nurse-authorizations.index'))->assertForbidden();
            $this->get(route('nurse-authorizations.show', $this->nurse))->assertForbidden();
            $this->put(route('nurse-authorizations.store', $this->nurse), ['test_scope' => 'ANY'])->assertForbidden();
            $this->delete(route('nurse-authorizations.destroy', $this->nurse))->assertForbidden();
        }
        $this->actingAs($this->nurse)->get(route('nurse-authorizations.index'))->assertForbidden();
        $this->put(route('nurse-authorizations.store', $this->nurse), ['test_scope' => 'ANY'])->assertForbidden();

        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('Nurse');
        $this->actingAs($other)->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->enable(['test_scope' => 'ANY'], null, $other);
        $this->actingAs($other)->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->get($this->url('show'))->assertSee('Save your assessment of this patient first.');
        $legacy = $this->legacyGrant();
        $this->post($this->url('request'), $this->payload($legacy))->assertNotFound();
        $this->assertNull($legacy->fresh()->used_at);

        $otherVisit = Visit::where('patient_id', '!=', $this->visit->patient_id)->firstOrFail();
        $this->actingAs($this->nurse)->post(route('followups.request', [$otherVisit->patient, $this->visit]), $this->payload())->assertNotFound();
        $this->assertSame(0, LaboratoryRecord::where('nurse_authorization_id', $authority->id)->count());
    }

    public function test_inactive_authorizing_doctor_blocks_orders_until_another_doctor_turns_it_on(): void
    {
        $stale = $this->enable(['test_scope' => 'ANY']);
        $this->doctor->is_active = false;
        $this->doctor->save();
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->get('/assessments?authorized=1')->assertViewHas('visits', fn ($visits) => $visits->isEmpty());
        $second = User::factory()->create(['is_active' => true]);
        $second->assignRole('Doctor / Medical Officer');
        $this->actingAs($second)->get(route('nurse-authorizations.index'))->assertSee('On · not usable');
        $this->get(route('nurse-authorizations.show', $this->nurse))->assertSee('On, but not usable');
        $fresh = $this->enable(['test_scope' => 'ANY'], $second);
        $this->assertSame($second->id, $stale->fresh()->revoked_by);
        $this->assertSame($second->id, $fresh->doctor_id);
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($second->id, LaboratoryRecord::where('nurse_authorization_id', $fresh->id)->sole()->authorizing_doctor_id);
    }

    public function test_stale_assessment_closed_visit_and_non_assessing_nurse_are_rejected(): void
    {
        $this->enable();
        $this->actingAs($this->nurse)->post($this->url('request'), array_replace($this->payload(), ['assessment_version' => 99]))->assertSessionHasErrors('followup');
        $vital = $this->visit->fresh()->vitalSign;
        $vital->recorded_by = $vital->updated_by = $this->doctor->id;
        $vital->save();
        $this->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->actingAs($this->doctor)->get($this->url('show'))->assertSee('Recorded by '.$this->doctor->name);
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->assertSame(0, LaboratoryRecord::whereNotNull('nurse_authorization_id')->count());
    }

    public function test_authorization_validation_and_request_tampering(): void
    {
        $this->actingAs($this->doctor);
        $store = route('nurse-authorizations.store', $this->nurse);
        $this->put($store, ['test_scope' => 'SELECTED', 'allowed_tests' => ['Invented']])->assertSessionHasErrors('allowed_tests.0');
        $this->put($store, ['test_scope' => 'SELECTED'])->assertSessionHasErrors('allowed_tests');
        $this->put($store, ['test_scope' => 'ANY', 'allowed_tests' => ['Urinalysis']])->assertSessionHasErrors('allowed_tests');
        $this->put($store, ['test_scope' => 'SELECTED', 'allowed_tests' => ['Urinalysis', 'Urinalysis']])->assertSessionHasErrors('allowed_tests.0');
        $this->put($store, ['test_scope' => 'ANY', 'doctor_id' => $this->nurse->id, 'nurse_id' => 1, 'allow_any_test' => 1])->assertSessionHasErrors(['doctor_id', 'nurse_id', 'allow_any_test']);
        $this->put($store, ['test_scope' => 'BOTH'])->assertSessionHasErrors('test_scope');
        $this->put(route('nurse-authorizations.store', $this->doctor), ['test_scope' => 'ANY'])->assertSessionHasErrors('nurse');
        $this->put(route('nurse-authorizations.store', User::where('email', 'pharmacist@rhu.test')->firstOrFail()), ['test_scope' => 'ANY'])->assertSessionHasErrors('nurse');
        $this->delete(route('nurse-authorizations.destroy', $this->nurse))->assertSessionHasErrors('nurse');
        $this->assertDatabaseCount('nurse_authorizations', 0);

        $this->enable();
        $this->actingAs($this->nurse);
        $this->post($this->url('request'), array_merge($this->payload(), ['authorizing_doctor_id' => $this->nurse->id, 'nurse_authorization_id' => 1]))->assertSessionHasErrors(['authorizing_doctor_id', 'nurse_authorization_id']);
        $this->post($this->url('request'), array_replace($this->payload(), ['request_reason' => '', 'confirm' => 0]))->assertSessionHasErrors(['request_reason', 'confirm']);
        $this->assertSame(0, LaboratoryRecord::whereNotNull('nurse_authorization_id')->count());
    }

    public function test_doctor_orders_several_tests_in_one_order(): void
    {
        $before = LaboratoryRecord::count();
        $payload = $this->payload(null, ['Urinalysis', 'Complete blood count (CBC)', 'Creatinine']);
        $this->actingAs($this->doctor)->post($this->url('request'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status', '3 laboratory tests sent to the laboratory as Pending.');
        $records = LaboratoryRecord::where('order_reference', $payload['submission_token'])->orderBy('id')->get();
        $this->assertSame(['Creatinine', 'Complete blood count (CBC)', 'Urinalysis'], $records->pluck('test_name')->all());
        foreach ($records as $record) {
            $this->assertSame([$this->visit->id, $this->doctor->id, $this->doctor->id, 'PENDING', $payload['request_reason']], [$record->visit_id, $record->created_by, $record->authorizing_doctor_id, $record->availability_status, $record->request_reason]);
        }
        $this->assertCount(3, $records->pluck('submission_token')->unique());
        $this->assertSame($records->pluck('id')->all(), Activity::where('description', 'followup.requested')->sole()->properties['laboratory_record_ids']);
        $this->post($this->url('request'), $payload)->assertSessionHasErrors('followup');
        $this->post($this->url('request'), $this->payload(null, ['Urinalysis', 'Urinalysis']))->assertSessionHasErrors('test_names.0');
        $this->post($this->url('request'), $this->payload(null, []))->assertSessionHasErrors('test_names');
        $this->post($this->url('request'), $this->payload(null, ['Invented']))->assertSessionHasErrors('test_names.0');
        $this->post($this->url('request'), array_merge($this->payload(), ['test_name' => 'Urinalysis', 'order_reference' => (string) Str::uuid()]))->assertSessionHasErrors(['test_name', 'order_reference']);
        $this->assertSame($before + 3, LaboratoryRecord::count());
    }

    public function test_pending_test_rejects_whole_order_without_partial_records(): void
    {
        $existing = LaboratoryRecord::pluck('id')->all();
        $this->actingAs($this->doctor)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        $this->post($this->url('request'), $this->payload(null, ['Complete blood count (CBC)', 'Urinalysis']))->assertSessionHasErrors('followup');
        $this->assertStringContainsString('Urinalysis', session('errors')->first('followup'));
        $this->assertSame(['Urinalysis'], LaboratoryRecord::whereNotIn('id', $existing)->pluck('test_name')->all());
    }

    public function test_nurse_chooses_only_within_permitted_tests(): void
    {
        $authority = $this->enable(['test_scope' => 'SELECTED', 'allowed_tests' => ['Urinalysis', 'Complete blood count (CBC)']]);
        $this->assertSame(['Complete blood count (CBC)', 'Urinalysis'], $authority->allowed_tests);
        $this->actingAs($this->nurse)->get($this->url('show'))->assertOk()->assertSee('Complete blood count (CBC), Urinalysis')
            ->assertSee('name="test_names[]" value="Urinalysis"', false)->assertDontSee('name="test_names[]" value="Creatinine"', false);
        $this->post($this->url('request'), $this->payload(null, ['Urinalysis', 'Creatinine']))->assertSessionHasErrors('test_names');
        $this->assertSame(0, LaboratoryRecord::where('nurse_authorization_id', $authority->id)->count());
        $this->post($this->url('request'), $this->payload(null, ['Complete blood count (CBC)', 'Urinalysis']))->assertSessionHasNoErrors()->assertSessionHas('status', '2 laboratory tests sent to the laboratory as Pending.');
    }

    public function test_changing_tests_creates_new_version_and_keeps_old_orders_linked(): void
    {
        $first = $this->enable();
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        $second = $this->enable(['test_scope' => 'ANY']);
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotNull($first->fresh()->revoked_at);
        $this->assertTrue($second->allow_any_test);
        $this->assertSame($first->id, LaboratoryRecord::where('test_name', 'Urinalysis')->latest('id')->first()->nurse_authorization_id);
        $this->assertSame(['on', 'changed'], $this->nurse->notifications()->oldest()->get()->pluck('data.state')->all());
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload(null, ['Creatinine']))->assertSessionHasNoErrors();
        $this->assertSame($second->id, LaboratoryRecord::where('test_name', 'Creatinine')->latest('id')->first()->nurse_authorization_id);
        $this->assertSame(1, NurseAuthorization::active()->where('nurse_id', $this->nurse->id)->count());
    }

    public function test_notifications_for_on_and_off_are_private_to_the_nurse(): void
    {
        $this->enable(['test_scope' => 'ANY']);
        $this->assertSame(1, $this->nurse->unreadNotifications()->count());
        $this->assertSame(0, DatabaseNotification::where('type', NurseOrderingChanged::class)->where('notifiable_id', '!=', $this->nurse->id)->count());
        $notification = $this->nurse->notifications()->sole();
        $this->assertSame(NurseOrderingChanged::class, $notification->type);
        $this->assertSame(['nurse_authorization_id', 'state', 'doctor_id'], array_keys($notification->data));
        $this->actingAs($this->nurse)->get(route('notifications.index'))->assertOk()
            ->assertSee($this->doctor->name.' (doctor) turned on your laboratory ordering authorization')->assertSee('Notifications, 1 unread');
        $this->actingAs(User::where('email', 'medtech@rhu.test')->firstOrFail())->get(route('notifications.open', $notification->id))->assertNotFound();
        $this->actingAs($this->nurse)->get(route('notifications.open', $notification->id))->assertRedirect(route('assessments.index', ['authorized' => 1]));
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($this->doctor)->delete(route('nurse-authorizations.destroy', $this->nurse))->assertSessionHasNoErrors();
        $this->actingAs($this->nurse)->get(route('notifications.index'))->assertSee($this->doctor->name.' (doctor) turned off your laboratory ordering authorization');
        $this->nurse->removeRole('Nurse');
        $this->nurse->assignRole('Pharmacist');
        $this->actingAs($this->nurse->fresh())->get(route('notifications.index'))->assertOk()->assertDontSee($this->doctor->name.' (doctor)');
        $this->get(route('notifications.open', $notification->id))->assertForbidden();
    }

    public function test_doctor_with_nurse_role_is_not_listed_and_cannot_authorize_themself(): void
    {
        $this->doctor->assignRole('Nurse');
        $this->actingAs($this->doctor)->get(route('nurse-authorizations.index'))->assertOk()->assertDontSee(route('nurse-authorizations.show', $this->doctor), false);
        $this->get(route('nurse-authorizations.show', $this->doctor))->assertNotFound();
        $this->put(route('nurse-authorizations.store', $this->doctor), ['test_scope' => 'ANY'])->assertSessionHasErrors('nurse');
        $this->assertDatabaseCount('nurse_authorizations', 0);
    }

    public function test_lab_request_slip_shows_order_with_nurse_and_authorizing_doctor(): void
    {
        $this->enable(['test_scope' => 'ANY']);
        $payload = $this->payload(null, ['Urinalysis', 'Fecalysis']);
        $this->actingAs($this->nurse)->post($this->url('request'), $payload)->assertSessionHasNoErrors();
        $slip = route('followups.slip', [$this->visit->patient, $this->visit, $payload['submission_token']]);
        $this->get($this->url('show'))->assertSee($slip, false)->assertSee('Order: 2 tests');
        $this->get($slip)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Add-on order by authorized nurse')->assertSee('Tests requested (2)')->assertSeeInOrder(['Urinalysis', 'Fecalysis'])
            ->assertSee('Ordered by (nurse)')->assertSee($this->nurse->name)->assertSee('Authorized by (doctor)')->assertSee($this->doctor->name)
            ->assertSee($this->visit->patient->full_name)->assertSee('not an official RHU form', false);
        $this->assertSame(1, Activity::where('description', 'followup.slip_viewed')->count());
        $this->actingAs($this->doctor)->get($slip)->assertOk();
        $this->actingAs(User::where('email', 'medtech@rhu.test')->firstOrFail())->get($slip)->assertOk();
        $this->get(route('laboratory.show', [$this->visit->patient, $this->visit]))->assertSee($slip, false);
        foreach (['pharmacist', 'admin', 'information'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get($slip)->assertForbidden();
        }
        $otherNurse = User::factory()->create(['is_active' => true]);
        $otherNurse->assignRole('Nurse');
        $this->actingAs($otherNurse)->get($slip)->assertForbidden();
        $this->actingAs($this->doctor)->get(route('followups.slip', [$this->visit->patient, $this->visit, (string) Str::uuid()]))->assertNotFound();
        $otherVisit = Visit::where('patient_id', '!=', $this->visit->patient_id)->firstOrFail();
        $this->get(route('followups.slip', [$otherVisit->patient, $this->visit, $payload['submission_token']]))->assertNotFound();
        $this->get(route('followups.slip', [$otherVisit->patient, $otherVisit, $payload['submission_token']]))->assertNotFound();

        $direct = $this->payload(null, ['Creatinine']);
        $this->post($this->url('request'), $direct)->assertSessionHasNoErrors();
        $this->get(route('followups.slip', [$this->visit->patient, $this->visit, $direct['submission_token']]))->assertOk()
            ->assertSee("Doctor's order")->assertSee('Ordered by (doctor)')->assertDontSee('Authorized by (doctor)');
    }

    public function test_legacy_visit_grant_still_works_once_and_can_be_withdrawn(): void
    {
        $legacy = $this->legacyGrant(['Urinalysis']);
        $this->assertSame(['Urinalysis'], $legacy->permittedTests());
        $this->actingAs($this->nurse)->get($this->url('show'))->assertSee('Your earlier visit-specific authorizations');
        $this->post($this->url('request'), $this->payload($legacy, ['Creatinine']))->assertSessionHasErrors('test_names');
        $this->post($this->url('request'), $this->payload($legacy))->assertSessionHasNoErrors();
        $this->assertNotNull($legacy->fresh()->used_at);
        $this->assertSame($legacy->id, LaboratoryRecord::where('followup_authorization_id', $legacy->id)->sole()->followup_authorization_id);
        $this->post($this->url('request'), $this->payload($legacy, ['Urinalysis']))->assertSessionHasErrors('followup');
        $unused = $this->legacyGrant(['Creatinine']);
        $this->actingAs($this->doctor)->post($this->url('revoke', $unused))->assertSessionHasNoErrors();
        $this->assertNotNull($unused->fresh()->revoked_at);
    }

    public function test_rollback_refuses_multi_test_data_and_reverses_cleanly_without_it(): void
    {
        $migration = require database_path('migrations/2026_10_25_110000_add_multi_test_laboratory_orders.php');
        $grant = $this->legacyGrant(['Urinalysis', 'Creatinine']);
        try {
            $migration->down();
            $this->fail('Rollback should refuse grants without a single named test.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('allow any test or several tests', $exception->getMessage());
        }
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload($grant, ['Urinalysis', 'Creatinine']))->assertSessionHasNoErrors();
        try {
            $migration->down();
            $this->fail('Rollback should refuse multi-test orders.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('multi-test orders', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('laboratory_records', 'order_reference'));

        LaboratoryRecord::where('followup_authorization_id', $grant->id)->delete();
        $grant->delete();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('laboratory_records', 'order_reference'));
        $this->assertFalse(Schema::hasColumn('followup_authorizations', 'allowed_tests'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('followup_authorizations', 'allow_any_test'));
    }

    public function test_deactivated_nurse_with_active_authority_stays_listed_and_can_be_turned_off(): void
    {
        $authority = $this->enable(['test_scope' => 'ANY']);
        $this->nurse->is_active = false;
        $this->nurse->save();
        $this->actingAs($this->doctor)->get(route('nurse-authorizations.index'))->assertOk()->assertSee($this->nurse->name)->assertSee('On · account not eligible');
        $this->get(route('nurse-authorizations.show', $this->nurse))->assertOk()
            ->assertSee('not currently an active nurse with follow-up access')->assertDontSee('Save and notify '.$this->nurse->name);
        $this->delete(route('nurse-authorizations.destroy', $this->nurse))->assertSessionHasNoErrors();
        $this->assertNotNull($authority->fresh()->revoked_at);
        $this->nurse->is_active = true;
        $this->nurse->save();
        $this->actingAs($this->nurse->fresh())->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
    }

    public function test_doctor_page_shows_assessment_recorder_and_latest_editor(): void
    {
        $this->enable(['test_scope' => 'ANY']);
        $editor = User::factory()->create(['is_active' => true, 'name' => 'Fictional Editing Nurse']);
        $editor->assignRole('Nurse');
        $this->assess($this->visit, $editor);
        $this->actingAs($this->doctor)->get($this->url('show'))
            ->assertSee('Recorded by '.$this->nurse->name)->assertSee('Last updated by Fictional Editing Nurse');
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_legacy_grant_counts_only_where_the_nurse_saved_the_assessment(): void
    {
        $this->legacyGrant();
        $this->actingAs($this->nurse)->get('/assessments?authorized=1')->assertViewHas('visits', fn ($visits) => $visits->total() === 1);
        $vital = $this->visit->fresh()->vitalSign;
        $vital->recorded_by = $vital->updated_by = $this->doctor->id;
        $vital->save();
        $this->get('/assessments?authorized=1')->assertViewHas('visits', fn ($visits) => $visits->isEmpty());
        $this->get('/dashboard')->assertDontSee('You can order laboratory tests');
    }

    public function test_notification_shows_authority_not_usable_after_doctor_loses_access(): void
    {
        $this->enable(['test_scope' => 'ANY']);
        $this->doctor->is_active = false;
        $this->doctor->save();
        $this->actingAs($this->nurse)->get(route('notifications.index'))->assertSee('Not usable: ask a doctor to renew it');
    }

    public function test_nurse_authorization_rollback_refuses_standing_orders(): void
    {
        $migration = require database_path('migrations/2026_10_25_130000_create_nurse_authorizations_table.php');
        $this->enable();
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        try {
            $migration->down();
            $this->fail('Rollback should refuse standing-authority orders.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('standing nurse authorization', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('laboratory_records', 'nurse_authorization_id'));
    }

    public function test_older_visit_grants_are_managed_on_the_nurse_page_not_the_visit_page(): void
    {
        $grant = $this->legacyGrant(['Urinalysis', 'Creatinine']);
        $this->actingAs($this->doctor)->get($this->url('show'))->assertDontSee('visit-specific authorizations')->assertDontSee('Revoke unused authority');
        $this->get(route('nurse-authorizations.index'))->assertSeeInOrder([$this->nurse->name, 'Off', '1']);
        $this->get(route('nurse-authorizations.show', $this->nurse))->assertOk()->assertSee('Older visit-specific authorizations')
            ->assertSee($this->visit->visit_number)->assertSee('Urinalysis, Creatinine')->assertSee('Fictional pre-upgrade instructions.');
        foreach (['admin', 'pharmacist'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->post(route('nurse-authorizations.withdraw-visit-grant', [$this->nurse, $grant]))->assertForbidden();
        }
        $this->actingAs($this->nurse)->post(route('nurse-authorizations.withdraw-visit-grant', [$this->nurse, $grant]))->assertForbidden();
        $otherNurse = User::factory()->create(['is_active' => true]);
        $otherNurse->assignRole('Nurse');
        $this->actingAs($this->doctor)->post(route('nurse-authorizations.withdraw-visit-grant', [$otherNurse, $grant]))->assertNotFound();
        $this->assertNull($grant->fresh()->revoked_at);
        $this->post(route('nurse-authorizations.withdraw-visit-grant', [$this->nurse, $grant]))->assertRedirect(route('nurse-authorizations.show', $this->nurse))->assertSessionHasNoErrors();
        $this->assertSame($this->doctor->id, $grant->fresh()->revoked_by);
        $this->post(route('nurse-authorizations.withdraw-visit-grant', [$this->nurse, $grant]))->assertSessionHasErrors('nurse');
        $this->actingAs($this->nurse)->get($this->url('show'))->assertDontSee('visit-specific authorizations');
        $this->post($this->url('request'), $this->payload($grant))->assertSessionHasErrors('followup');
    }
}
