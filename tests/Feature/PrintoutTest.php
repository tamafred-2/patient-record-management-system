<?php

namespace Tests\Feature;

use App\Models\DispositionRecord;
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

class PrintoutTest extends TestCase
{
    use RefreshDatabase;

    private array $users = [];

    private Patient $patient;

    private Patient $other;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        foreach (['staff' => 'Information Staff', 'nurse' => 'Nurse', 'doctor' => 'Doctor / Medical Officer', 'medtech' => 'MedTech', 'pharmacist' => 'Pharmacist', 'admin' => 'System Admin'] as $key => $role) {
            $this->users[$key] = User::factory()->create(['is_active' => true, 'name' => 'Fictional '.ucfirst($key)]);
            $this->users[$key]->assignRole($role);
        }
        $this->actingAs($this->users['staff']);
        foreach (['Print', 'Other'] as $last) {
            $this->post('/patients', ['birth_date' => '1990-05-05', 'sex' => 'Female', 'barangay' => 'Example Barangay', 'municipality' => 'Calasiao', 'province' => 'Pangasinan', 'first_name' => 'Fictional', 'last_name' => $last])->assertRedirect();
        }
        $this->patient = Patient::where('last_name', 'Print')->firstOrFail();
        $this->other = Patient::where('last_name', 'Other')->firstOrFail();
        $this->post(route('patients.visits.store', $this->patient), ['service_id' => Service::first()->id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertSessionHasNoErrors();
        $this->visit = Visit::latest('id')->firstOrFail();
        $this->actingAs($this->users['nurse'])->put(route('assessments.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'temperature' => 38.5, 'assessment_summary' => 'Fictional summary.'])->assertSessionHasNoErrors();
        $this->actingAs($this->users['doctor'])->put(route('itr.save', [$this->patient, $this->visit]), ['lock_version' => 0, 'vitals_version' => 1, 'assessment' => 'Fictional assessment', 'diagnoses' => "Fictional diagnosis one\nFictional diagnosis two"])->assertSessionHasNoErrors();
    }

    private function as(string $key): self
    {
        return $this->actingAs($this->users[$key]);
    }

    public function test_issued_prescription_prints_for_doctor_and_pharmacist_only(): void
    {
        $print = route('prints.prescription', [$this->patient, $this->visit]);
        $this->put(route('prescriptions.show', [$this->patient, $this->visit]), ['lock_version' => 0, 'items' => [['medicine_name' => 'Fictional medicine', 'strength' => '500 mg', 'dosage' => '1 tablet', 'frequency' => '3 times a day', 'duration' => '7 days', 'quantity_prescribed' => '21', 'instructions' => 'After meals']]])->assertSessionHasNoErrors();
        $this->get($print)->assertNotFound();
        $this->post(route('prescriptions.issue', [$this->patient, $this->visit]), ['lock_version' => 1, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->get(route('prescriptions.show', [$this->patient, $this->visit]))->assertSee($print, false);
        foreach (['doctor', 'pharmacist'] as $role) {
            $this->as($role)->get($print)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('Prescription')->assertSee('Fictional medicine')->assertSee('500 mg')->assertSee('1 tablet, 3 times a day')->assertSee('7 days')->assertSee('21')
                ->assertSee('After meals')->assertSee('Fictional diagnosis one; Fictional diagnosis two')->assertSee('Fictional Doctor')->assertSee('not an official RHU form layout');
        }
        $this->as('pharmacist')->get(route('pharmacy.show', [$this->patient, $this->visit]))->assertSee($print, false);
        foreach (['nurse', 'medtech', 'admin', 'staff'] as $role) {
            $this->as($role)->get($print)->assertForbidden();
        }
        $this->as('doctor')->get(route('prints.prescription', [$this->other, $this->visit]))->assertNotFound();
        $this->assertSame(2, Activity::where('description', 'prescription.print_viewed')->count());
    }

    public function test_referral_and_higher_facility_advice_print_but_certificate_requests_do_not(): void
    {
        $this->as('doctor');
        foreach (['EMERGENCY_REFERRAL', 'CERTIFICATE_REQUEST'] as $type) {
            $this->post(route('dispositions.store', [$this->patient, $this->visit]), ['type' => $type, 'reason' => 'Fictional reason for '.$type, 'facility_name' => $type === 'CERTIFICATE_REQUEST' ? null : 'Fictional Provincial Hospital', 'specialty' => $type === 'CERTIFICATE_REQUEST' ? null : 'Internal medicine', 'remarks' => 'Fictional remarks.', 'submission_token' => (string) Str::uuid(), 'confirm' => 1])->assertSessionHasNoErrors();
        }
        $referral = DispositionRecord::where('type', 'EMERGENCY_REFERRAL')->firstOrFail();
        $certificate = DispositionRecord::where('type', 'CERTIFICATE_REQUEST')->firstOrFail();
        $print = route('prints.referral', [$this->patient, $this->visit, $referral]);
        $this->get(route('dispositions.show', [$this->patient, $this->visit]))->assertSee($print, false)->assertDontSee(route('prints.referral', [$this->patient, $this->visit, $certificate]), false);
        $this->get($print)->assertOk()->assertSee('Emergency referral slip')->assertSee('Fictional Provincial Hospital')->assertSee('Internal medicine')
            ->assertSee('Fictional reason for EMERGENCY_REFERRAL')->assertSee('BP 120/80 mmHg')->assertSee('Temp 38.50 °C')->assertSee('Fictional diagnosis one')->assertSee('Fictional Doctor');
        $this->get(route('prints.referral', [$this->patient, $this->visit, $certificate]))->assertNotFound();
        $this->get(route('prints.referral', [$this->other, $this->visit, $referral]))->assertNotFound();
        foreach (['nurse', 'medtech', 'pharmacist', 'admin', 'staff'] as $role) {
            $this->as($role)->get($print)->assertForbidden();
        }
        $this->assertSame(1, Activity::where('description', 'disposition.print_viewed')->count());
    }

    public function test_external_laboratory_request_prints_only_for_externally_advised_tests(): void
    {
        $this->visit->service_id = Service::where('seed_key', 'LABORATORY')->firstOrFail()->id;
        $this->visit->save();
        $this->as('medtech');
        $lab = fn (string $status, array $extra = []) => $this->post(route('laboratory.store', [$this->patient, $this->visit]), ['test_name' => 'Blood typing', 'availability_status' => $status, 'lock_version' => 0, 'submission_token' => (string) Str::uuid()] + $extra)->assertSessionHasNoErrors();
        $lab('EXTERNAL_ADVISED', ['external_advice' => 'Fictional advice: reagent unavailable, use an accredited laboratory.']);
        $lab('PENDING', ['test_name' => 'Urinalysis']);
        $external = LaboratoryRecord::where('availability_status', 'EXTERNAL_ADVISED')->firstOrFail();
        $pending = LaboratoryRecord::where('availability_status', 'PENDING')->firstOrFail();
        $print = route('prints.external-lab', [$this->patient, $this->visit, $external]);
        $this->get(route('laboratory.show', [$this->patient, $this->visit]))->assertSee($print, false);
        $this->get($print)->assertOk()->assertSee('External laboratory request')->assertSee('Blood typing')->assertSee('Fictional advice: reagent unavailable')->assertSee('Fictional Medtech');
        $this->as('doctor')->get($print)->assertOk();
        $this->get(route('prints.external-lab', [$this->patient, $this->visit, $pending]))->assertNotFound();
        $this->get(route('prints.external-lab', [$this->other, $this->visit, $external]))->assertNotFound();
        foreach (['nurse', 'pharmacist', 'admin', 'staff'] as $role) {
            $this->as($role)->get($print)->assertForbidden();
        }
        $this->assertSame(2, Activity::where('description', 'laboratory.external_slip_viewed')->count());
    }

    public function test_print_center_lists_only_permitted_documents_per_role(): void
    {
        $this->put(route('prescriptions.show', [$this->patient, $this->visit]), ['lock_version' => 0, 'items' => [['medicine_name' => 'Fictional medicine', 'dosage' => '1 tablet', 'frequency' => 'Once a day', 'quantity_prescribed' => '5']]])->assertSessionHasNoErrors();
        $this->post(route('prescriptions.issue', [$this->patient, $this->visit]), ['lock_version' => 1, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->post(route('dispositions.store', [$this->patient, $this->visit]), ['type' => 'FORMAL_REFERRAL', 'reason' => 'Fictional referral.', 'facility_name' => 'Fictional Hospital', 'submission_token' => (string) Str::uuid(), 'confirm' => 1])->assertSessionHasNoErrors();
        $page = route('print-center.patient', $this->patient);

        $this->get($page)->assertOk()->assertSee('Documents &amp; printouts', false)
            ->assertSee(route('visits.pdf', [$this->patient, $this->visit]), false)->assertSee(route('prints.prescription', [$this->patient, $this->visit]), false)
            ->assertSee('Fictional Hospital')->assertSee('Prepare a medical certificate')->assertDontSee(route('visits.stub', [$this->patient, $this->visit]), false);
        $this->get(route('consultations.show', [$this->patient, $this->visit]))->assertSee(route('prints.prescription', [$this->patient, $this->visit]), false);

        $this->as('staff')->get($page)->assertOk()->assertSee(route('visits.stub', [$this->patient, $this->visit]), false)
            ->assertDontSee(route('prints.prescription', [$this->patient, $this->visit]), false)->assertDontSee(route('visits.pdf', [$this->patient, $this->visit]), false)->assertDontSee('Fictional Hospital');
        $this->get(route('patients.visits.show', [$this->patient, $this->visit]))->assertSee(route('visits.stub', [$this->patient, $this->visit]), false);
        $this->get(route('patients.show', $this->patient))->assertSee($page, false);
        $this->get(route('history.show', $this->patient))->assertSee($page, false)->assertSee(route('print-center.summary', $this->patient), false);

        foreach (['nurse', 'medtech', 'pharmacist', 'admin'] as $role) {
            $this->as($role)->get($page)->assertForbidden();
            $this->get(route('print-center.summary', $this->patient))->assertForbidden();
        }
    }

    public function test_patient_summary_shows_clinical_details_only_to_clinical_viewers(): void
    {
        $summary = route('print-center.summary', $this->patient);
        $this->as('doctor')->get($summary)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Patient summary')->assertSee($this->visit->visit_number)->assertSee('Diagnosis: Fictional diagnosis one; Fictional diagnosis two')->assertSee('BP 120/80');
        $this->as('staff')->get($summary)->assertOk()->assertSee($this->visit->visit_number)->assertDontSee('Fictional diagnosis')->assertDontSee('BP 120/80');
        $audits = Activity::where('description', 'patient.summary_printed')->get();
        $this->assertCount(2, $audits);
        $this->assertStringNotContainsString('Fictional', $audits->pluck('properties')->toJson());
    }

    public function test_print_center_includes_an_older_visit_requested_from_the_timeline(): void
    {
        $this->as('staff');
        $older = $this->visit;
        foreach (range(1, 26) as $number) {
            $visit = new Visit;
            $visit->patient()->associate($this->patient);
            $visit->service_id = $older->service_id;
            $visit->visit_date = now('Asia/Manila')->toDateString();
            $visit->created_by = $this->users['staff']->id;
            $visit->status = 'COMPLETED';
            $visit->save();
        }
        $page = route('print-center.patient', $this->patient);
        $this->get($page)->assertOk()->assertDontSee('id="visit-'.$older->id.'"', false)->assertSee('Only the latest 25 visits are listed.');
        $this->get(route('print-center.patient', [$this->patient, 'visit' => $older->id]))->assertOk()->assertSee('id="visit-'.$older->id.'"', false)->assertSee(route('visits.stub', [$this->patient, $older]), false);
        $this->get(route('history.show', $this->patient))->assertOk();
        $otherVisit = new Visit;
        $otherVisit->patient()->associate($this->other);
        $otherVisit->service_id = $older->service_id;
        $otherVisit->visit_date = now('Asia/Manila')->toDateString();
        $otherVisit->created_by = $this->users['staff']->id;
        $otherVisit->status = 'OPEN';
        $otherVisit->save();
        $this->get(route('print-center.patient', [$this->patient, 'visit' => $otherVisit->id]))->assertNotFound();
    }
}
