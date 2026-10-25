<?php

namespace Tests\Feature;

use App\Models\AssessmentRequest;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ReassessmentTest extends TestCase
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
        $this->visit = Visit::doesntHave('vitalSign')->whereDate('visit_date', now('Asia/Manila')->toDateString())->firstOrFail();
        $this->doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
        $this->nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
    }

    private function saveAssessment(array $extra = []): void
    {
        $this->actingAs($this->nurse)->put(route('assessments.save', [$this->visit->patient, $this->visit]), array_replace([
            'lock_version' => $this->visit->fresh()->vitalSign?->lock_version ?? 0,
            'pulse_rate' => 72, 'assessment_summary' => 'Fictional detailed nursing observations.',
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();
    }

    private function requestAssessment(): AssessmentRequest
    {
        $this->actingAs($this->doctor)->post(route('reassessments.store', [$this->visit->patient, $this->visit]), [
            'assessment_version' => $this->visit->fresh()->vitalSign->lock_version,
            'reason' => 'Fictional request to repeat measurements before review.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return AssessmentRequest::latest('id')->firstOrFail();
    }

    public function test_doctor_lists_wait_for_vitals_and_show_nurse_summary(): void
    {
        $this->actingAs($this->doctor)->get('/consultations')->assertDontSee($this->visit->visit_number);
        $this->get('/dashboard')->assertDontSee($this->visit->visit_number);
        $this->get('/dashboard/visits')->assertDontSee($this->visit->visit_number);
        $this->saveAssessment();
        $this->actingAs($this->doctor)->get('/consultations')->assertSee($this->visit->visit_number);
        $this->get('/dashboard')->assertSee($this->visit->visit_number);
        $this->get(route('consultations.show', [$this->visit->patient, $this->visit]))->assertSee('Fictional detailed nursing observations.')->assertSee($this->nurse->name);
    }

    public function test_request_is_completed_only_by_explicit_nurse_confirmation_and_blocks_visit_closure(): void
    {
        $this->saveAssessment();
        $entry = $this->requestAssessment();
        $this->get(route('visits.completion', [$this->visit->patient, $this->visit]))->assertViewHas('review', fn ($review) => $review['blocked'] && $review['pendingAssessments'] === 1);
        $this->actingAs($this->nurse)->get('/assessments?reassessment=1')->assertSee($this->visit->visit_number)->assertSee('Reassessment requested');
        $this->get(route('assessments.edit', [$this->visit->patient, $this->visit]))->assertSee($entry->reason);
        $this->saveAssessment(); // A form opened before the doctor's request must not resolve it.
        $this->assertSame('PENDING', $entry->fresh()->status);
        $this->saveAssessment(['complete_reassessment' => 1, 'reassessment_request_id' => $entry->id, 'pulse_rate' => 75, 'assessment_summary' => 'Fictional repeat assessment completed.']);
        $this->assertSame('COMPLETED', $entry->fresh()->status);
        $this->assertSame($this->nurse->id, $entry->fresh()->resolved_by);
        $this->assertEquals(3, $entry->fresh()->completed_version);
        $this->actingAs($this->doctor)->get(route('consultations.show', [$this->visit->patient, $this->visit]))->assertSee('Fictional repeat assessment completed.')->assertSee('Completed');
        $this->get(route('visits.completion', [$this->visit->patient, $this->visit]))->assertViewHas('review', fn ($review) => ! $review['blocked']);
        $this->assertStringNotContainsString('Fictional repeat assessment', Activity::all()->toJson());
        $this->assertStringNotContainsString($entry->reason, Activity::all()->toJson());
    }

    public function test_duplicate_stale_cross_visit_and_unauthorized_requests_are_rejected(): void
    {
        $this->saveAssessment();
        $url = route('reassessments.store', [$this->visit->patient, $this->visit]);
        $this->actingAs($this->nurse)->post($url, ['reason' => 'Repeat', 'assessment_version' => 1])->assertForbidden();
        $this->actingAs($this->doctor)->post($url, ['reason' => 'Repeat', 'assessment_version' => 2])->assertSessionHasErrors('reassessment');
        $entry = $this->requestAssessment();
        $this->post($url, ['reason' => 'Repeat', 'assessment_version' => 1])->assertSessionHasErrors('reassessment');
        $other = Visit::where('id', '!=', $this->visit->id)->firstOrFail();
        $this->post(route('reassessments.store', [$other->patient, $this->visit]), ['reason' => 'Repeat', 'assessment_version' => 1])->assertNotFound();
        $this->post(route('reassessments.cancel', [$other->patient, $other, $entry]), ['resolution_note' => 'Wrong visit'])->assertNotFound();
        $this->actingAs($this->nurse)->put(route('assessments.save', [$this->visit->patient, $this->visit]), ['lock_version' => 1, 'pulse_rate' => 80, 'assessment_summary' => 'Fictional', 'complete_reassessment' => 1, 'reassessment_request_id' => $entry->id + 100])->assertSessionHasErrors('reassessment');
        $this->assertSame('PENDING', $entry->fresh()->status);
        $this->assertDatabaseCount('assessment_requests', 1);
    }

    public function test_withdrawal_preserves_history_and_invalidates_old_nurse_completion(): void
    {
        $this->saveAssessment();
        $entry = $this->requestAssessment();
        $this->post(route('reassessments.cancel', [$this->visit->patient, $this->visit, $entry]), ['resolution_note' => 'Fictional reason for withdrawal'])->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $entry->fresh()->status);
        $new = $this->requestAssessment();
        $this->actingAs($this->nurse)->put(route('assessments.save', [$this->visit->patient, $this->visit]), ['lock_version' => 1, 'pulse_rate' => 80, 'assessment_summary' => 'Fictional', 'complete_reassessment' => 1, 'reassessment_request_id' => $entry->id])->assertSessionHasErrors('reassessment');
        $this->assertSame('PENDING', $new->fresh()->status);
    }

    public function test_summary_required_and_closed_visit_cannot_receive_requests(): void
    {
        $this->actingAs($this->nurse)->put(route('assessments.save', [$this->visit->patient, $this->visit]), ['lock_version' => 0, 'pulse_rate' => 72])->assertSessionHasErrors('assessment_summary');
        $this->saveAssessment();
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->actingAs($this->doctor)->post(route('reassessments.store', [$this->visit->patient, $this->visit]), ['reason' => 'Repeat', 'assessment_version' => 1])->assertSessionHasErrors('reassessment');
        $this->assertDatabaseCount('assessment_requests', 0);
    }

    public function test_pending_request_blocks_both_stale_and_current_completion_submissions(): void
    {
        $this->saveAssessment();
        $this->actingAs($this->doctor);
        $url = route('visits.completion', [$this->visit->patient, $this->visit]);
        $before = $this->get($url)->viewData('review')['version'];
        $this->requestAssessment();
        $after = $this->get($url)->viewData('review')['version'];
        foreach ([$before, $after] as $version) {
            $this->post(route('visits.complete', [$this->visit->patient, $this->visit]), ['confirm' => 1, 'review_version' => $version])->assertSessionHasErrors('visit');
        }
        $this->assertSame('OPEN', $this->visit->fresh()->status);
    }

    public function test_doctor_with_nurse_access_keeps_unassessed_visits_in_nurse_list_only(): void
    {
        $this->doctor->assignRole('Nurse');
        $this->actingAs($this->doctor);
        $page = $this->get('/dashboard')->assertOk();
        $this->assertFalse($page->viewData('todayVisits')->getCollection()->contains('id', $this->visit->id));
        $this->assertTrue($page->viewData('departmentBatches')['assessments']->getCollection()->contains('id', $this->visit->id));
        $this->get('/dashboard/visits?section=assessments')->assertSee($this->visit->visit_number);
    }
}
