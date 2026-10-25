<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use App\Support\DispensingQuantities;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    private function role(string $name): void
    {
        $this->actingAs(User::where('email', $name.'@rhu.test')->sole());
    }

    public function test_reopening_preserves_completion_and_rejects_stale_cycles_and_wrong_roles(): void
    {
        $visit = Visit::orderBy('id')->skip(1)->firstOrFail();
        $this->role('information');
        $url = route('visits.completion', [$visit->patient, $visit]);
        $version = $this->get($url)->viewData('review')['version'];
        $this->post($url, ['confirm' => 1, 'review_version' => $version, 'completion_remarks' => 'Fictional completion'])->assertSessionHasNoErrors();
        $original = $visit->fresh();
        $version = $this->get($url)->viewData('review')['version'];
        $reopen = route('visits.reopen', [$visit->patient, $visit]);
        $data = ['confirm' => 1, 'review_version' => $version, 'reason' => 'Fictional correction'];
        $this->post($reopen, [...$data, 'reason' => '  '])->assertSessionHasErrors('reason');
        foreach (['admin', 'nurse', 'pharmacist'] as $role) {
            $this->role($role);
            $this->post($reopen, $data)->assertForbidden();
        }
        $this->role('doctor');
        $other = Visit::where('id', '!=', $visit->id)->firstOrFail();
        $this->post(route('visits.reopen', [$other->patient, $visit]), $data)->assertNotFound();
        $this->post($reopen, $data)->assertSessionHasNoErrors();
        $this->assertSame('OPEN', $visit->fresh()->status);
        $this->assertNull($visit->fresh()->completed_at);
        $this->assertDatabaseHas('visit_reopenings', ['visit_id' => $visit->id, 'previous_completed_by' => $original->completed_by, 'previous_completion_remarks' => 'Fictional completion']);
        $this->post($reopen, $data)->assertSessionHasErrors('visit');
        $this->post($url, ['confirm' => 1, 'review_version' => $this->get($url)->viewData('review')['version']])->assertSessionHasNoErrors();
        $this->post($reopen, $data)->assertSessionHasErrors('visit');
        $this->assertDatabaseCount('visit_reopenings', 1);
    }

    public function test_void_keeps_original_release_and_recalculates_quantities_without_double_void(): void
    {
        $visit = Visit::firstOrFail();
        $rx = $visit->prescription;
        $release = $rx->dispensings()->firstOrFail();
        $before = $release->items->toArray();
        $url = route('pharmacy.void', [$visit->patient, $visit, $release]);
        $data = ['confirm' => 1, 'reason' => 'Fictional entry correction', 'lock_version' => $rx->lock_version];
        $this->role('doctor');
        $this->post($url, $data)->assertForbidden();
        $this->role('pharmacist');
        $this->post($url, [...$data, 'reason' => ''])->assertSessionHasErrors('reason');
        $this->post($url, [...$data, 'lock_version' => 999])->assertSessionHasErrors('lock_version');
        $other = Visit::orderBy('id')->skip(1)->firstOrFail();
        $this->post(route('pharmacy.void', [$other->patient, $visit, $release]), $data)->assertNotFound();
        $this->post(route('pharmacy.void', [$other->patient, $other, $release]), $data)->assertNotFound();
        $visit->forceFill(['status' => 'COMPLETED'])->save();
        $this->post($url, $data)->assertSessionHasErrors('visit');
        $visit->forceFill(['status' => 'OPEN'])->save();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertNotNull($release->fresh()->voided_at);
        $this->assertSame($before, $release->fresh()->items->toArray());
        foreach (DispensingQuantities::forPrescription($rx->fresh()) as $line) {
            $this->assertSame(0, $line['released']);
        }
        $this->post($url, [...$data, 'lock_version' => $rx->fresh()->lock_version])->assertSessionHasErrors('lock_version');
        $this->get(route('pharmacy.show', [$visit->patient, $visit]))->assertOk()->assertSee('Voided by')->assertSee('Not dispensed');
        $this->assertDatabaseCount('dispensings', 1);
    }
}
