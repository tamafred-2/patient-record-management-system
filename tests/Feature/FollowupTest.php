<?php

namespace Tests\Feature;

use App\Models\FollowupAuthorization;
use App\Models\LaboratoryRecord;
use App\Models\NurseAuthorization;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\WorkflowAlert;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

// Doctors and nurses both order laboratory tests directly (post-defense revision: no doctor authorization step).
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
            'lock_version' => $visit->vitalSign?->lock_version ?? 0, 'temperature' => 37, 'assessment_summary' => 'Fictional observations for a laboratory order test.',
        ])->assertSessionHasNoErrors();
    }

    private function url(string $action, mixed $extra = null, ?Visit $visit = null): string
    {
        $visit ??= $this->visit;

        return route('followups.'.$action, array_filter([$visit->patient, $visit, $extra]));
    }

    private function payload(array $tests = ['Urinalysis'], ?Visit $visit = null): array
    {
        return [
            'request_reason' => 'Fictional findings for testing.', 'assessment_version' => ($visit ?? $this->visit)->fresh()->vitalSign->lock_version,
            'submission_token' => (string) Str::uuid(), 'confirm' => 1, 'test_names' => $tests,
        ];
    }

    private function alerts(User $user, string $kind): int
    {
        return $user->notifications()->where('type', WorkflowAlert::class)->get()->where('data.kind', $kind)->count();
    }

    // A record created before nurses could order directly, under a doctor's per-visit authorization.
    private function legacyOrder(): LaboratoryRecord
    {
        $grant = new FollowupAuthorization;
        $grant->forceFill(['visit_id' => $this->visit->id, 'doctor_id' => $this->doctor->id, 'nurse_id' => $this->nurse->id, 'test_name' => 'Urinalysis',
            'allowed_tests' => ['Urinalysis'], 'instructions' => 'Fictional pre-revision instructions.', 'used_at' => now()])->save();

        return LaboratoryRecord::forceCreate(['visit_id' => $this->visit->id, 'submission_token' => (string) Str::uuid(), 'order_reference' => (string) Str::uuid(),
            'test_name' => 'Urinalysis', 'availability_status' => 'PENDING', 'request_reason' => 'Fictional legacy reason.', 'created_by' => $this->nurse->id,
            'updated_by' => $this->nurse->id, 'authorizing_doctor_id' => $this->doctor->id, 'followup_authorization_id' => $grant->id]);
    }

    public function test_nurse_orders_any_test_directly_without_doctor_authorization(): void
    {
        $this->assertSame(0, NurseAuthorization::count());
        $this->actingAs($this->nurse)->get($this->url('show'))->assertOk()->assertSee('Order laboratory tests')->assertSee('Creatinine')
            ->assertDontSee('not currently authorized');
        $payload = $this->payload(['Urinalysis', 'Creatinine']);
        $this->post($this->url('request'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status', '2 laboratory tests sent to the laboratory as Pending.');
        $records = LaboratoryRecord::where('order_reference', $payload['submission_token'])->get();
        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $this->assertSame([$this->nurse->id, null, null, null, 'PENDING'], [$record->created_by, $record->authorizing_doctor_id, $record->nurse_authorization_id, $record->followup_authorization_id, $record->availability_status]);
            $this->assertTrue($record->isOrdered() && $record->orderedByNurse() && ! $record->isNurseOrder());
        }
        $this->assertSame('nurse', Activity::where('description', 'followup.requested')->sole()->properties['ordered_by_role']);
        $this->assertGreaterThan(0, $this->alerts(User::where('email', 'medtech@rhu.test')->firstOrFail(), 'lab_ordered'));
        $this->get($this->url('show'))->assertSee('Ordered by nurse:')->assertSee($this->nurse->name)->assertDontSee('Authorized by doctor');

        // Any active nurse can order, not only the one who saved the assessment.
        $other = User::factory()->create(['is_active' => true, 'name' => 'Fictional Second Nurse']);
        $other->assignRole('Nurse');
        $this->actingAs($other)->post($this->url('request'), $this->payload(['Complete blood count (CBC)']))->assertSessionHasNoErrors();
        $this->assertSame($other->id, LaboratoryRecord::where('test_name', 'Complete blood count (CBC)')->latest('id')->value('created_by'));
    }

    public function test_doctor_orders_several_tests_in_one_order(): void
    {
        $before = LaboratoryRecord::count();
        $payload = $this->payload(['Urinalysis', 'Complete blood count (CBC)', 'Creatinine']);
        $this->actingAs($this->doctor)->post($this->url('request'), $payload)->assertSessionHasNoErrors()->assertSessionHas('status', '3 laboratory tests sent to the laboratory as Pending.');
        $records = LaboratoryRecord::where('order_reference', $payload['submission_token'])->orderBy('id')->get();
        $this->assertSame(['Creatinine', 'Complete blood count (CBC)', 'Urinalysis'], $records->pluck('test_name')->all());
        foreach ($records as $record) {
            $this->assertSame([$this->visit->id, $this->doctor->id, $this->doctor->id, 'PENDING', $payload['request_reason']], [$record->visit_id, $record->created_by, $record->authorizing_doctor_id, $record->availability_status, $record->request_reason]);
            $this->assertFalse($record->orderedByNurse());
        }
        $this->assertCount(3, $records->pluck('submission_token')->unique());
        $this->assertSame($records->pluck('id')->all(), Activity::where('description', 'followup.requested')->sole()->properties['laboratory_record_ids']);
        $this->post($this->url('request'), $payload)->assertSessionHasErrors('followup');
        $this->post($this->url('request'), $this->payload(['Urinalysis', 'Urinalysis']))->assertSessionHasErrors('test_names.0');
        $this->post($this->url('request'), $this->payload([]))->assertSessionHasErrors('test_names');
        $this->post($this->url('request'), $this->payload(['Invented']))->assertSessionHasErrors('test_names.0');
        $this->assertSame($before + 3, LaboratoryRecord::count());
        $this->get($this->url('show'))->assertSee('Ordered by doctor:');
    }

    public function test_pending_test_rejects_whole_order_without_partial_records(): void
    {
        $existing = LaboratoryRecord::pluck('id')->all();
        $this->actingAs($this->nurse)->post($this->url('request'), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($this->doctor)->post($this->url('request'), $this->payload(['Complete blood count (CBC)', 'Urinalysis']))->assertSessionHasErrors('followup');
        $this->assertStringContainsString('Urinalysis', session('errors')->first('followup'));
        $this->assertSame(['Urinalysis'], LaboratoryRecord::whereNotIn('id', $existing)->pluck('test_name')->all());
    }

    public function test_other_roles_tampering_and_removed_authorization_page_are_rejected(): void
    {
        foreach (['admin', 'information', 'medtech', 'pharmacist', 'midwife'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->getJson($this->url('show'))->assertForbidden();
            $this->post($this->url('request'), $this->payload())->assertForbidden();
        }
        $this->actingAs($this->doctor)->get('/nurse-authorizations')->assertNotFound();
        $this->actingAs($this->nurse);
        foreach (['authorization_id' => 1, 'nurse_authorization_id' => 1, 'authorizing_doctor_id' => $this->doctor->id, 'created_by' => $this->doctor->id, 'order_reference' => (string) Str::uuid()] as $field => $value) {
            $this->post($this->url('request'), [...$this->payload(), $field => $value])->assertSessionHasErrors($field);
        }
        $other = Visit::where('patient_id', '!=', $this->visit->patient_id)->firstOrFail();
        $this->post(route('followups.request', [$other->patient, $this->visit]), $this->payload())->assertNotFound();
        $this->assertSame(0, LaboratoryRecord::whereNotNull('order_reference')->count());
    }

    public function test_stale_assessment_missing_assessment_and_closed_visit_are_rejected(): void
    {
        $stale = $this->payload();
        $this->assess($this->visit);
        $this->actingAs($this->nurse)->post($this->url('request'), $stale)->assertSessionHasErrors('followup');

        $unassessed = Visit::where('status', Visit::OPEN)->doesntHave('vitalSign')->firstOrFail();
        $this->get($this->url('show', null, $unassessed))->assertOk()->assertSee('Laboratory orders require an open visit with a saved assessment.');

        $this->visit->forceFill(['status' => Visit::COMPLETED])->save();
        $this->post($this->url('request'), $this->payload())->assertSessionHasErrors('followup');
        $this->assertSame(0, LaboratoryRecord::whereNotNull('order_reference')->count());
    }

    public function test_result_notifies_whoever_ordered_the_test(): void
    {
        $medtech = User::where('email', 'medtech@rhu.test')->firstOrFail();
        foreach ([$this->nurse, $this->doctor] as $orderer) {
            $test = $orderer->is($this->nurse) ? 'Urinalysis' : 'Complete blood count (CBC)';
            $this->actingAs($orderer)->post($this->url('request'), $this->payload([$test]))->assertSessionHasNoErrors();
            $record = LaboratoryRecord::where('test_name', $test)->whereNotNull('order_reference')->latest('id')->firstOrFail();
            $this->actingAs($medtech)->put(route('laboratory.update', [$this->visit->patient, $this->visit, $record]), [
                'test_name' => $test, 'availability_status' => 'PERFORMED_RHU', 'result_rows' => [['measurement' => 'Synthetic measurement', 'value' => 'Synthetic result']], 'lock_version' => 1,
            ])->assertSessionHasNoErrors();
            $this->assertSame(1, $this->alerts($orderer, 'lab_completed'));
        }
    }

    public function test_slip_and_lab_screens_show_who_ordered(): void
    {
        $payload = $this->payload(['Urinalysis', 'Fecalysis']);
        $this->actingAs($this->nurse)->post($this->url('request'), $payload)->assertSessionHasNoErrors();
        $slip = $this->url('slip', $payload['submission_token']);
        $this->get($this->url('show'))->assertSee($slip, false)->assertSee('Order: 2 tests');
        $this->get($slip)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Ordered by nurse')->assertSee('Ordered by (nurse)')->assertSee($this->nurse->name)->assertSee('Tests requested (2)')
            ->assertSeeInOrder(['Urinalysis', 'Fecalysis'])->assertDontSee('Authorized by (doctor)')->assertSee('not an official RHU form', false);
        $this->actingAs($this->doctor)->get($slip)->assertOk();
        $this->actingAs(User::where('email', 'medtech@rhu.test')->firstOrFail())->get($slip)->assertOk();
        $this->get(route('laboratory.show', [$this->visit->patient, $this->visit]))->assertSee('Ordered by nurse:')->assertSee($slip, false);
        $otherNurse = User::factory()->create(['is_active' => true]);
        $otherNurse->assignRole('Nurse');
        $this->actingAs($otherNurse)->get($slip)->assertOk();
        foreach (['pharmacist', 'admin', 'information'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->getJson($slip)->assertForbidden();
        }
        $this->actingAs($this->doctor)->get($this->url('slip', (string) Str::uuid()))->assertNotFound();

        $direct = $this->payload(['Creatinine']);
        $this->post($this->url('request'), $direct)->assertSessionHasNoErrors();
        $this->get($this->url('slip', $direct['submission_token']))->assertOk()->assertSee('Doctor’s order')->assertSee('Ordered by (doctor)');
    }

    public function test_orders_placed_under_an_older_doctor_authorization_keep_that_history(): void
    {
        $record = $this->legacyOrder();
        $this->assertTrue($record->isNurseOrder() && $record->orderedByNurse());
        $this->actingAs($this->doctor)->get($this->url('show'))->assertSee('Ordered by nurse:')->assertSee('Authorized by doctor:')->assertSee('Fictional pre-revision instructions.');
        $this->get($this->url('slip', $record->order_reference))->assertSee('Authorized by (doctor)')->assertSee($this->doctor->name);
        $this->assertSame($this->doctor->id, $record->orderer()->id);
    }

    public function test_nurse_screens_link_to_ordering(): void
    {
        $this->actingAs($this->nurse)->get(route('assessments.edit', [$this->visit->patient, $this->visit]))->assertSee('Order lab tests')->assertSee($this->url('show'), false);
        $this->get(route('assessments.index', ['date' => $this->visit->visit_date->toDateString()]))->assertSee('Order lab tests');
        $this->get(route('assessments.index', ['authorized' => 1]))->assertOk();
        $this->actingAs($this->doctor)->get('/dashboard')->assertDontSee('Nurse authorization');
    }

    public function test_doctor_page_shows_assessment_recorder_and_latest_editor(): void
    {
        $editor = User::factory()->create(['is_active' => true, 'name' => 'Fictional Editing Nurse']);
        $editor->assignRole('Nurse');
        $this->assess($this->visit, $editor);
        $this->actingAs($this->doctor)->get($this->url('show'))
            ->assertSee('Recorded by '.$this->nurse->name)->assertSee('Last updated by Fictional Editing Nurse');
    }

    public function test_rollback_refuses_multi_test_data_and_reverses_cleanly_without_it(): void
    {
        $migration = require database_path('migrations/2026_10_25_110000_add_multi_test_laboratory_orders.php');
        // An older authorization that allowed several tests, used for a two-test order.
        $record = $this->legacyOrder();
        $grant = FollowupAuthorization::findOrFail($record->followup_authorization_id);
        $grant->forceFill(['test_name' => null, 'allowed_tests' => ['Urinalysis', 'Creatinine']])->save();
        LaboratoryRecord::forceCreate([...$record->only(['visit_id', 'order_reference', 'availability_status', 'request_reason', 'created_by', 'updated_by', 'authorizing_doctor_id', 'followup_authorization_id']),
            'submission_token' => (string) Str::uuid(), 'test_name' => 'Creatinine']);
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
        $migration->up();
        $this->assertTrue(Schema::hasColumn('laboratory_records', 'order_reference'));
    }
}
