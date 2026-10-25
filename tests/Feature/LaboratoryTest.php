<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LaboratoryTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private Patient $patient;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('Information Staff');
        $this->actingAs($staff)->post('/patients', ['birth_date' => '2000-01-10', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => 'Verification'])->assertRedirect();
        $this->patient = Patient::firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::where('seed_key', 'LABORATORY')->firstOrFail()->id, 'visit_date' => now('Asia/Manila')->toDateString(), 'queue_reference' => 'TEST-LAB'])->assertSessionHasNoErrors()->assertRedirect();
        $this->visit = Visit::firstOrFail();
        $this->officer = User::factory()->create();
        $this->officer->assignRole('MedTech');
        $this->actingAs($this->officer);
    }

    private function url(?Patient $patient = null): string
    {
        return route('laboratory.show', [$patient ?? $this->patient, $this->visit]);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['test_name' => 'Complete blood count (CBC)', 'availability_status' => 'PENDING', 'lock_version' => 0, 'submission_token' => (string) Str::uuid()], $extra);
    }

    public function test_medtech_records_edits_multiple_services_and_audits_without_results(): void
    {
        $this->get('/laboratory')->assertOk()->assertSee('Fictional Verification');
        $data = $this->data();
        $this->post($this->url(), $data)->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $this->assertEquals($this->visit->id, $record->visit_id);
        $this->assertEquals($this->officer->id, $record->created_by);
        $this->post($this->url(), $data)->assertSessionHasErrors('submission_token');
        $edit = route('laboratory.edit', [$this->patient, $this->visit, $record]);
        $this->get($edit)->assertOk();
        $this->put($edit, ['test_name' => 'Complete blood count (CBC)', 'availability_status' => 'EXTERNAL_ADVISED', 'external_advice' => '<script>example advice</script>', 'lock_version' => 1])->assertSessionHasNoErrors();
        $this->get($this->url())->assertSee('&lt;script&gt;example advice&lt;/script&gt;', false)->assertDontSee('<script>example advice</script>', false);
        $this->put($edit, ['test_name' => 'Complete blood count (CBC)', 'availability_status' => 'PERFORMED_RHU', 'result_rows' => [['measurement' => 'Synthetic measurement', 'value' => 'Synthetic result']], 'lock_version' => 1])->assertSessionHasErrors('lock_version');
        $this->put($edit, ['test_name' => 'Complete blood count (CBC)', 'availability_status' => 'PERFORMED_RHU', 'result_rows' => [['measurement' => 'Synthetic measurement', 'value' => 'Synthetic result']], 'lock_version' => 2])->assertSessionHasNoErrors();
        $this->assertNull($record->fresh()->external_advice);
        $this->post($this->url(), $this->data(['test_name' => 'Urinalysis']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laboratory_records', 2);
        $audit = Activity::where('description', 'laboratory.updated')->firstOrFail();
        $this->assertSame(['visit_id', 'version'], array_keys($audit->properties->all()));
        $this->assertSame('OPEN', $this->visit->fresh()->status);
    }

    public function test_pending_request_is_completed_with_structured_results_and_cannot_revert(): void
    {
        $this->post($this->url(), $this->data(['availability_status' => 'PERFORMED_RHU']))->assertSessionHasErrors('availability_status');
        $this->post($this->url(), $this->data(['test_name' => 'Unlisted test']))->assertSessionHasErrors('test_name');
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $edit = route('laboratory.edit', [$this->patient, $this->visit, $record]);
        $data = ['test_name' => $record->test_name, 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1];
        $this->put($edit, $data)->assertSessionHasErrors('result_rows');
        $rows = [['measurement' => 'Synthetic component', 'value' => '<script>Synthetic result</script>', 'unit' => 'example unit', 'reference_range' => 'source report range']];
        $this->put($edit, $data + ['result_rows' => $rows, 'specimen' => 'Synthetic specimen'])->assertSessionHasNoErrors();
        $this->assertSame($rows, $record->fresh()->result_rows);
        $this->assertNotNull($record->fresh()->completed_at);
        $this->assertSame('PERFORMED_RHU', $record->fresh()->availability_status);
        $this->get($this->url())->assertSee('Completed at RHU')->assertSee('Synthetic component')->assertSee('source report range')->assertSee('&lt;script&gt;Synthetic result&lt;/script&gt;', false)->assertDontSee('<script>Synthetic result</script>', false);
        $this->assertSame(['visit_id', 'version'], array_keys(Activity::where('description', 'laboratory.updated')->firstOrFail()->properties->all()));
        $this->put($edit, ['test_name' => $record->test_name, 'availability_status' => 'PENDING', 'lock_version' => 2])->assertSessionHasErrors('availability_status');
        $this->assertSame($rows, $record->fresh()->result_rows);
        $this->assertSame('OPEN', $this->visit->fresh()->status);
    }

    public function test_historical_names_and_text_results_are_preserved_when_adding_result_rows(): void
    {
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $record->forceFill(['test_name' => 'Historical test', 'availability_status' => 'PERFORMED_RHU', 'result' => 'Historical text result'])->save();
        $this->get($this->url())->assertSee('Historical text result');
        $edit = route('laboratory.edit', [$this->patient, $this->visit, $record]);
        $this->get($edit)->assertSee('Historical test (existing record)');
        $this->put($edit, ['test_name' => 'Historical test', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1, 'result_rows' => [['measurement' => 'Historical component', 'value' => 'Copied value']]])->assertSessionHasNoErrors();
        $this->assertSame('Historical text result', $record->fresh()->result);
        $this->get($this->url())->assertSee('Historical text result')->assertSee('Copied value');
    }

    public function test_unreported_template_rows_are_not_saved_and_flags_are_validated(): void
    {
        $this->post($this->url(), $this->data(['test_name' => 'Urinalysis']))->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $edit = route('laboratory.edit', [$this->patient, $this->visit, $record]);
        $data = ['test_name' => 'Urinalysis', 'availability_status' => 'PERFORMED_RHU', 'lock_version' => 1];
        $blank = ['measurement' => 'Color', 'value' => '', 'unit' => '', 'reference_range' => '', 'flag' => ''];
        $this->put($edit, $data + ['result_rows' => [$blank]])->assertSessionHasErrors('result_rows');
        $this->put($edit, $data + ['result_rows' => [['measurement' => 'Protein', 'value' => '2+', 'flag' => 'Infection']]])->assertSessionHasErrors('result_rows.0.flag');
        $reported = ['measurement' => 'Bacteria', 'value' => 'Many', 'flag' => 'Abnormal'];
        $this->put($edit, $data + ['result_rows' => [$blank, $reported]])->assertSessionHasNoErrors();
        $this->assertSame([$reported], $record->fresh()->result_rows);
        $this->get($this->url())->assertSee('Many')->assertSee('Abnormal');
    }

    public function test_with_records_filter_respects_dates_search_and_archives(): void
    {
        $this->get('/laboratory?record=with_records')->assertOk()->assertDontSee('Fictional Verification');
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $this->get('/laboratory?record=with_records')->assertOk()->assertSee('Fictional Verification');
        $this->get('/laboratory?record=with_records&date=2000-01-01')->assertOk()->assertDontSee('Fictional Verification');
        $this->get('/laboratory?record=with_records&all=1&date=2000-01-01&search=Verification')->assertOk()->assertSee('Fictional Verification');
        $this->get('/laboratory?record=with_records&search=Unmatched')->assertOk()->assertDontSee('Fictional Verification');
        $this->get('/laboratory?record=invalid')->assertRedirect()->assertSessionHasErrors('record');
        $this->patient->delete();
        $this->get('/laboratory?record=with_records&all=1')->assertOk()->assertDontSee('Fictional Verification');
    }

    public function test_validation_and_foreign_record_are_rejected(): void
    {
        foreach ([['test_name' => ''], ['availability_status' => 'UNKNOWN'], ['availability_status' => 'EXTERNAL_ADVISED'], ['external_advice' => 'Wrong status'], ['patient_id' => 999], ['visit_id' => 999], ['result' => 'Unsupported result']] as $extra) {
            $this->post($this->url(), $this->data($extra))->assertSessionHasErrors();
        }
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        $other = $this->patient->replicate();
        $other->patient_number = 'RHU-LAB-OTHER';
        $other->save();
        $this->get($this->url($other))->assertNotFound();
        $this->post($this->url($other), $this->data())->assertNotFound();
        $second = $this->visit->replicate();
        $second->visit_number = 'V-LAB-OTHER';
        $second->save();
        $wrong = route('laboratory.edit', [$this->patient, $second, $record]);
        $this->get($wrong)->assertNotFound();
        $this->put($wrong, ['test_name' => 'Wrong', 'availability_status' => 'PENDING', 'lock_version' => 1])->assertNotFound();
    }

    public function test_denied_roles_read_only_and_clinical_boundary(): void
    {
        $this->get('/patients')->assertForbidden();
        $this->get(route('itr.edit', [$this->patient, $this->visit]))->assertForbidden();
        $this->post($this->url(), $this->data())->assertSessionHasNoErrors();
        $record = LaboratoryRecord::firstOrFail();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'Doctor / Medical Officer', 'Pharmacist', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            $this->get('/laboratory')->assertForbidden();
            $this->get($this->url())->assertForbidden();
            $this->post($this->url(), $this->data())->assertForbidden();
            $this->put(route('laboratory.update', [$this->patient, $this->visit, $record]), ['lock_version' => 1])->assertForbidden();
        }
        $reader = User::factory()->create();
        $reader->givePermissionTo('laboratory.view');
        $this->actingAs($reader);
        $this->get($this->url())->assertOk();
        $this->post($this->url(), $this->data())->assertForbidden();
    }

    public function test_date_closed_and_archived_visits(): void
    {
        $this->get('/laboratory?date=2000-01-01')->assertOk()->assertDontSee('Fictional Verification');
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->post($this->url(), $this->data())->assertSessionHasErrors('visit');
        $this->patient->delete();
        $this->get($this->url())->assertNotFound();
        $this->get('/laboratory')->assertDontSee('Fictional Verification');
    }
}
