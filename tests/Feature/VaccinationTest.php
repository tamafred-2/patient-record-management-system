<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class VaccinationTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->visit = Visit::orderBy('id')->skip(1)->firstOrFail();
        $this->visit->forceFill(['service_id' => Service::where('seed_key', 'VACCINATION')->firstOrFail()->id, 'queue_reference' => 'VAC-TEST-001'])->save();
        $this->actingAs(User::where('email', 'midwife@rhu.test')->firstOrFail());
    }

    private function url(): string
    {
        return route('vaccinations.show', [$this->visit->patient, $this->visit]);
    }

    private function data(): array
    {
        return ['vaccine_name' => 'Fictional vaccine', 'dose' => 'Fictional documented dose',
            'administered_on' => $this->visit->visit_date->toDateString(), 'batch_number' => 'FICTIONAL-LOT',
            'administered_by' => 'Fictional administering staff', 'confirm' => 1,
            'lock_version' => 0, 'submission_token' => (string) Str::uuid()];
    }

    public function test_midwife_records_edits_and_reviews_vaccination_only_history(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Today')->assertSee('VAC-TEST-001');
        $this->get(route('vaccinations.index', ['search' => 'VAC-TEST']))->assertOk()->assertSee($this->visit->visit_number);
        $this->get($this->url())->assertOk()->assertSee('Patient vaccination history');
        $data = $this->data();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $record = VaccinationRecord::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals(auth()->id(), $record->created_by);
        $this->post($this->url(), $data)->assertSessionHasErrors('submission_token');
        unset($data['submission_token']);
        $data['lock_version'] = 1;
        $data['remarks'] = '<script>fictional</script>';
        $edit = route('vaccinations.edit', [$this->visit->patient, $this->visit, $record]);
        $this->put($edit, $data)->assertSessionHasNoErrors();
        $this->put($edit, $data)->assertSessionHasErrors('lock_version');
        $this->get($this->url())->assertSee('Fictional vaccine')->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $this->assertSame(2, $record->fresh()->lock_version);
        $this->assertSame(['visit_id', 'version'], array_keys(Activity::where('description', 'vaccination.updated')->firstOrFail()->properties->all()));
        $this->get('/patients')->assertForbidden();
        $this->get('/consultations')->assertForbidden();
        $this->get('/pharmacy')->assertForbidden();
        $this->get(route('visits.completion', [$this->visit->patient, $this->visit]))->assertForbidden();
    }

    public function test_permissions_parent_scoping_closed_archived_and_non_vaccination_visits(): void
    {
        $other = Visit::firstOrFail();
        $this->get(route('vaccinations.show', [$other->patient, $this->visit]))->assertNotFound();
        $this->post(route('vaccinations.show', [$other->patient, $this->visit]), $this->data())->assertNotFound();
        $this->get(route('vaccinations.show', [$other->patient, $other]))->assertNotFound();
        $this->post(route('vaccinations.show', [$other->patient, $other]), $this->data())->assertNotFound();
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->url(), $this->data())->assertSessionHasErrors('visit');
        $this->get($this->url())->assertOk()->assertSee('read-only');
        foreach (['admin', 'doctor', 'information', 'nurse', 'pharmacist', 'medtech'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
            $this->get('/vaccinations')->assertForbidden();
            $this->post($this->url(), $this->data())->assertForbidden();
            $this->get('/dashboard')->assertDontSee('Open vaccination record');
        }
        $this->actingAs(User::where('email', 'midwife@rhu.test')->firstOrFail());
        $url = $this->url();
        $this->visit->patient->delete();
        $this->get($url)->assertNotFound();
        $this->get('/dashboard')->assertDontSee('VAC-TEST-001');
        $this->assertDatabaseCount('vaccination_records', 0);
    }

    public function test_validation_and_vaccination_changes_invalidate_completion_review(): void
    {
        foreach ([['vaccine_name' => ''], ['dose' => ''], ['administered_by' => ''], ['confirm' => 0],
            ['administered_on' => '2000-01-01'], ['next_appointment' => '2000-01-01'],
            ['batch_number' => str_repeat('x', 101)], ['remarks' => str_repeat('x', 1001)],
            ['visit_id' => 999], ['created_by' => 999], ['lock_version' => 1]] as $invalid) {
            $this->post($this->url(), array_replace($this->data(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('vaccination_records', 0);
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
}
