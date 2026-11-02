<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Post-defense revision: search and filters on every record list.
class RecordListFilterTest extends TestCase
{
    use RefreshDatabase;

    private Visit $assessed;

    private Visit $waiting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->assessed = Visit::where('visit_number', 'DEMO-VISIT-1')->firstOrFail();
        $this->waiting = Visit::where('visit_number', 'DEMO-VISIT-2')->firstOrFail();
        $this->waiting->forceFill(['priority' => 'EMERGENCY', 'priority_rank' => 2])->save();
    }

    private function as(string $role): self
    {
        return $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
    }

    public function test_nurse_filters_by_vital_signs_and_priority(): void
    {
        $this->assertNotNull($this->assessed->vitalSign);
        $this->assertNull($this->waiting->vitalSign);
        $this->as('nurse')->get('/assessments?all=1&vitals=pending')->assertOk()->assertSee($this->waiting->visit_number)->assertDontSee($this->assessed->visit_number);
        $this->get('/assessments?all=1&vitals=recorded')->assertSee($this->assessed->visit_number)->assertDontSee($this->waiting->visit_number);
        $this->get('/assessments?all=1&priority=EMERGENCY')->assertSee($this->waiting->visit_number)->assertDontSee($this->assessed->visit_number);
        $this->get('/assessments?all=1&priority=EMERGENCY&vitals=recorded')->assertDontSee($this->waiting->visit_number)->assertDontSee($this->assessed->visit_number);
        $this->get('/assessments?vitals=sometimes')->assertSessionHasErrors('vitals');
        $this->get('/assessments?priority=URGENT')->assertSessionHasErrors('priority');
        // The date form keeps the chosen filters.
        $this->get('/assessments?vitals=pending')->assertSee('<input type="hidden" name="vitals" value="pending">', false);
    }

    public function test_laboratory_pharmacy_and_confirmation_filters(): void
    {
        LaboratoryRecord::where('visit_id', $this->assessed->id)->update(['availability_status' => 'PERFORMED_RHU']);
        $this->as('medtech')->get('/laboratory?all=1&lab_status=PERFORMED_RHU')->assertOk()->assertSee($this->assessed->visit_number);
        $this->get('/laboratory?all=1&lab_status=PENDING')->assertDontSee($this->assessed->visit_number);
        $this->get('/laboratory?lab_status=DONE')->assertSessionHasErrors('lab_status');

        $this->as('pharmacist')->get('/pharmacy?all=1&release=entered')->assertOk()->assertSee($this->assessed->prescription->prescription_number);
        $this->get('/pharmacy?all=1&release=awaiting')->assertDontSee($this->assessed->prescription->prescription_number);

        $this->as('admin')->get('/eligibility?all=1&confirmation=unchecked')->assertOk()->assertSee($this->waiting->visit_number)->assertDontSee($this->assessed->visit_number);
        $this->get('/eligibility?all=1&confirmation=with')->assertSee($this->assessed->visit_number);
    }

    public function test_patient_visits_filter_by_status_and_priority(): void
    {
        $this->assessed->forceFill(['status' => Visit::COMPLETED])->save();
        $this->as('information')->get('/visits?all=1&status=COMPLETED')->assertOk()->assertSee($this->assessed->visit_number)->assertDontSee($this->waiting->visit_number);
        $this->get('/visits?all=1&status=OPEN&priority=EMERGENCY')->assertSee($this->waiting->visit_number)->assertDontSee($this->assessed->visit_number);
        $this->get('/visits?status=CLOSED')->assertSessionHasErrors('status');
    }
}
