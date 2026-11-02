<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Post-defense revision: every department's records appear on the patient file, each section only for roles
// that already work with that kind of record.
class PatientFileTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->visit = Visit::where('visit_number', 'DEMO-VISIT-1')->firstOrFail();
        LaboratoryRecord::where('visit_id', $this->visit->id)->update(['availability_status' => 'PERFORMED_RHU',
            'result_rows' => json_encode([['measurement' => 'Fictional measurement', 'value' => 'Fictional lab value', 'unit' => 'g/L', 'reference_range' => '1-2', 'flag' => 'High']])]);
    }

    private function page(string $role)
    {
        return $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get(route('history.show', $this->visit->patient))->assertOk();
    }

    public function test_each_role_sees_only_its_own_kinds_of_records(): void
    {
        $vitals = '120/80 mmHg';
        $itr = 'Fictional demonstration only. No clinical advice.';
        $lab = 'Fictional demonstration test';
        $result = 'Fictional lab value';
        $medicine = 'Fictional demonstration medicine';
        $referral = 'Fictional demonstration request';

        $this->page('doctor')->assertSee('Patient file')->assertSee($vitals)->assertSee($itr)->assertSee($lab)->assertSee($result)->assertSee($medicine)->assertSee('Released 4')->assertSee($referral)->assertSee('PhilHealth:');
        $this->page('nurse')->assertSee($vitals)->assertSee($lab)->assertDontSee($result)->assertDontSee($itr)->assertDontSee($medicine)->assertDontSee($referral);
        $this->page('medtech')->assertSee($lab)->assertSee($result)->assertDontSee($vitals)->assertDontSee($itr)->assertDontSee($medicine);
        $this->page('pharmacist')->assertSee($medicine)->assertSee('Released 4')->assertDontSee($vitals)->assertDontSee($itr)->assertDontSee($lab);
        $this->page('midwife')->assertSee($vitals)->assertDontSee($itr)->assertDontSee($lab)->assertDontSee($medicine);
        $this->page('information')->assertSee($this->visit->visit_number)->assertSee('Clinical details are visible to clinical staff only.')->assertDontSee($vitals)->assertDontSee($medicine);
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail())->getJson(route('history.show', $this->visit->patient))->assertForbidden();
    }

    public function test_open_visit_link_goes_to_a_page_the_role_can_open(): void
    {
        foreach (['doctor', 'nurse', 'medtech', 'pharmacist', 'information'] as $role) {
            $response = $this->page($role);
            preg_match('/href="([^"]+)">Open visit</', $response->getContent(), $match);
            $this->assertNotEmpty($match, "$role has an Open visit link");
            $this->get(html_entity_decode($match[1]))->assertOk();
        }
    }
}
