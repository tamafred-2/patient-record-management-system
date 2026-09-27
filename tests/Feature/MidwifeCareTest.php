<?php

namespace Tests\Feature;

use App\Models\MidwifeCareRecord;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MidwifeCareTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->visit = Visit::orderBy('id')->skip(1)->firstOrFail();
        $this->visit->forceFill(['service_id' => Service::where('seed_key', 'MIDWIFE_CARE')->firstOrFail()->id, 'queue_reference' => 'CARE-TEST-001'])->save();
        $this->actingAs(User::where('email', 'midwife@rhu.test')->firstOrFail());
    }

    private function url(): string
    {
        return route('midwife-care.show', [$this->visit->patient, $this->visit]);
    }

    private function data(): array
    {
        return ['care_type' => 'PRENATAL', 'purpose' => 'Fictional care purpose',
            'services_provided' => 'Fictional service', 'confirm' => 1,
            'lock_version' => 0, 'submission_token' => (string) Str::uuid()];
    }

    public function test_midwife_records_edits_and_reviews_care_only_history(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Today')->assertSee('CARE-TEST-001');
        $this->get(route('midwife-care.index', ['search' => 'CARE-TEST']))->assertOk()->assertSee($this->visit->visit_number);
        $this->get($this->url())->assertOk()->assertSee('Patient Midwife care history');
        $data = $this->data();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $record = MidwifeCareRecord::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals(auth()->id(), $record->created_by);
        $this->post($this->url(), $data)->assertSessionHasErrors('submission_token');
        unset($data['submission_token']);
        $data['lock_version'] = 1;
        $data['notes'] = '<script>fictional</script>';
        $edit = route('midwife-care.edit', [$this->visit->patient, $this->visit, $record]);
        $this->put($edit, $data)->assertSessionHasNoErrors();
        $this->put($edit, $data)->assertSessionHasErrors('lock_version');
        $this->get($this->url())->assertSee('Fictional care purpose')->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $this->assertSame(2, $record->fresh()->lock_version);
        $this->assertSame(['visit_id', 'version'], array_keys(Activity::where('description', 'midwife-care.updated')->firstOrFail()->properties->all()));
        $this->get('/patients')->assertForbidden();
        $this->get('/consultations')->assertForbidden();
        $this->get('/pharmacy')->assertForbidden();
        $this->get(route('visits.completion', [$this->visit->patient, $this->visit]))->assertForbidden();
    }

    public function test_permissions_parent_scoping_closed_archived_and_non_care_visits(): void
    {
        $other = Visit::firstOrFail();
        $this->get(route('midwife-care.show', [$other->patient, $this->visit]))->assertNotFound();
        $this->post(route('midwife-care.show', [$other->patient, $this->visit]), $this->data())->assertNotFound();
        $this->get(route('midwife-care.show', [$other->patient, $other]))->assertNotFound();
        $this->post(route('midwife-care.show', [$other->patient, $other]), $this->data())->assertNotFound();
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->url(), $this->data())->assertSessionHasErrors('visit');
        $this->get($this->url())->assertOk()->assertSee('read-only');
        foreach (['admin', 'doctor', 'information', 'nurse', 'pharmacist', 'medtech'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->get('/midwife-care')->assertForbidden();
            $this->post($this->url(), $this->data())->assertForbidden();
            $this->get('/dashboard')->assertDontSee('Open care record');
        }
        $this->actingAs(User::where('email', 'midwife@rhu.test')->firstOrFail());
        $url = $this->url();
        $this->visit->patient->delete();
        $this->get($url)->assertNotFound();
        $this->get('/dashboard')->assertDontSee('CARE-TEST-001');
        $this->assertDatabaseCount('midwife_care_records', 0);
    }

    public function test_validation_and_care_changes_invalidate_completion_review(): void
    {
        foreach ([['purpose' => ''], ['care_type' => 'DELIVERY'], ['confirm' => 0],
            ['follow_up_on' => '2000-01-01'], ['care_type' => 'COMMUNITY'], ['location' => 'Not applicable'],
            ['purpose' => str_repeat('x', 3001)], ['notes' => str_repeat('x', 3001)],
            ['visit_id' => 999], ['created_by' => 999], ['lock_version' => 1]] as $invalid) {
            $this->post($this->url(), array_replace($this->data(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('midwife_care_records', 0);
        $this->actingAs(User::where('email', 'information@rhu.test')->firstOrFail());
        $url = route('visits.completion', [$this->visit->patient, $this->visit]);
        $version = $this->get($url)->assertOk()->viewData('review')['version'];
        $this->actingAs(User::where('email', 'midwife@rhu.test')->firstOrFail());
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->actingAs(User::where('email', 'information@rhu.test')->firstOrFail());
        $this->post($url, ['confirm' => 1, 'review_version' => $version])->assertSessionHasErrors('visit');
        $version = $this->get($url)->assertOk()->viewData('review')['version'];
        $this->post($url, ['confirm' => 1, 'review_version' => $version])->assertSessionHasNoErrors();
        $this->assertSame('COMPLETED', $this->visit->fresh()->status);
    }

    public function test_all_six_care_types_and_cross_visit_entry_scoping(): void
    {
        foreach (array_keys(MidwifeCareRecord::TYPES) as $type) {
            $data = array_replace($this->data(), ['care_type' => $type]);
            if ($type === 'COMMUNITY') {
                $data['location'] = 'Fictional home location';
            }
            $this->post($this->url(), $data)->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('midwife_care_records', 6);
        $record = MidwifeCareRecord::firstOrFail();
        $other = Visit::firstOrFail();
        $other->service_id = $this->visit->service_id;
        $other->save();
        $wrong = route('midwife-care.edit', [$other->patient, $other, $record]);
        $this->get($wrong)->assertNotFound();
        $data = $this->data();
        unset($data['submission_token']);
        $data['lock_version'] = 1;
        $this->put($wrong, $data)->assertNotFound();
        $this->get(route('midwife-care.show', [$other->patient, $other]))->assertDontSee('Fictional care purpose');
        $samePatientVisit = $other->replicate();
        $samePatientVisit->patient_id = $this->visit->patient_id;
        $samePatientVisit->visit_number = 'CARE-SECOND-VISIT';
        $samePatientVisit->save();
        $this->get(route('midwife-care.show', [$this->visit->patient, $samePatientVisit]))->assertSee('Fictional care purpose');
    }
}
