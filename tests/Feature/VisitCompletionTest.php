<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VisitCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    private function role(string $role): User
    {
        $user = User::where('email', $role.'@rhu.test')->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    private function url(Visit $visit): string
    {
        return route('visits.completion', [$visit->patient, $visit]);
    }

    private function version(Visit $visit): string
    {
        return $this->get($this->url($visit))->assertOk()->viewData('review')['version'];
    }

    public function test_staff_and_doctor_complete_with_attribution_and_no_double_completion(): void
    {
        foreach (['information', 'doctor'] as $index => $role) {
            $user = $this->role($role);
            $visit = Visit::orderBy('id')->skip($index + 1)->firstOrFail();
            $data = ['confirm' => 1, 'review_version' => $this->version($visit)];
            $this->post($this->url($visit), array_diff_key($data, ['confirm' => true]))->assertSessionHasErrors('confirm');
            $this->post($this->url($visit), $data)->assertSessionHasNoErrors();
            $this->assertSame('COMPLETED', $visit->fresh()->status);
            $this->assertEquals($user->id, $visit->fresh()->completed_by);
            $this->assertNotNull($visit->fresh()->completed_at);
            $this->post($this->url($visit), $data)->assertSessionHasErrors('visit');
            $this->get($this->url($visit))->assertSee('Completed by');
        }
        $this->assertSame(2, Activity::where('description', 'visit.completed')->count());
        $this->role('admin');
        $this->get(route('visits.index', ['from' => now('Asia/Manila')->subDay()->toDateString(), 'to' => now('Asia/Manila')->toDateString()]))->assertSee('COMPLETED');
    }

    public function test_pending_services_stale_reviews_and_unreleased_medicines(): void
    {
        $this->role('doctor');
        $visit = Visit::firstOrFail();
        $url = $this->url($visit);
        $this->post($url, ['confirm' => 1, 'review_version' => $this->version($visit)])->assertSessionHasErrors('visit');
        $lab = $visit->laboratoryRecords()->firstOrFail();
        $lab->availability_status = 'PERFORMED_RHU';
        $lab->lock_version++;
        $lab->save();
        $version = $this->version($visit);
        $this->post($url, ['confirm' => 1, 'review_version' => $version])->assertSessionHasErrors('completion_remarks');
        $rx = $visit->prescription;
        $rx->status = 'DRAFT';
        $rx->lock_version++;
        $rx->save();
        $this->post($url, ['confirm' => 1, 'review_version' => $version, 'completion_remarks' => 'Fictional unavailable supply'])->assertSessionHasErrors('visit');
        $this->post($url, ['confirm' => 1, 'review_version' => $this->version($visit)])->assertSessionHasErrors('visit');
        $rx->status = 'ISSUED';
        $rx->lock_version++;
        $rx->save();
        $this->post($url, ['confirm' => 1, 'review_version' => $this->version($visit), 'completion_remarks' => 'Fictional unavailable supply, checked with Pharmacy'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('dispensings', 1);
        $this->assertSame('COMPLETED', $visit->fresh()->status);
        $audit = Activity::where('description', 'visit.completed')->firstOrFail();
        $this->assertArrayNotHasKey('completion_remarks', $audit->properties->all());
        $this->role('pharmacist');
        $this->get(route('pharmacy.show', [$visit->patient, $visit]))->assertOk()->assertDontSee('Record release');
        $item = $rx->items()->firstOrFail();
        $this->post(route('pharmacy.store', [$visit->patient, $visit]), ['confirm' => 1, 'lock_version' => $rx->lock_version, 'quantities' => [$item->id => 1]])->assertSessionHasErrors('visit');
    }

    public function test_unauthorized_cross_patient_archived_and_forged_completion_are_rejected(): void
    {
        $visit = Visit::orderBy('id')->skip(1)->firstOrFail();
        foreach (['admin', 'nurse', 'pharmacist', 'medtech', 'midwife'] as $role) {
            $this->role($role);
            $this->get($this->url($visit))->assertForbidden();
            $this->post($this->url($visit), ['confirm' => 1])->assertForbidden();
        }
        $this->role('information');
        $wrong = route('visits.completion', [Visit::first()->patient, $visit]);
        $this->get($wrong)->assertNotFound();
        $this->post($wrong, ['confirm' => 1])->assertNotFound();
        $this->post($this->url($visit), ['confirm' => 1, 'review_version' => $this->version($visit), 'completed_by' => 999])->assertSessionHasErrors('completed_by');
        $url = $this->url($visit);
        $visit->patient->delete();
        $this->get($url)->assertNotFound();
        $this->post($url, ['confirm' => 1])->assertNotFound();
        $this->assertSame('OPEN', $visit->fresh()->status);
    }
}
