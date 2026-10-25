<?php

namespace Tests\Feature;

use App\Models\MedicalCertificate;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MedicalCertificateTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;

    private Patient $patient;

    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->doctor = User::factory()->create();
        $this->doctor->assignRole('Doctor / Medical Officer');
        $this->patient = Patient::forceCreate(['created_by' => $this->doctor->id, 'duplicate_key' => (string) Str::uuid(), 'first_name' => 'Fictional', 'last_name' => 'Certificate', 'birth_date' => '2000-01-10', 'barangay' => 'Example', 'municipality' => 'Calasiao', 'province' => 'Pangasinan']);
        $service = Service::create(['name' => 'Medical Certificate', 'code' => 'CERTIFICATE']);
        $this->visit = Visit::forceCreate(['patient_id' => $this->patient->id, 'service_id' => $service->id, 'created_by' => $this->doctor->id, 'visit_date' => '2026-10-24', 'status' => 'OPEN']);
        $this->actingAs($this->doctor);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['template' => 'consultation', 'body' => 'Fictional Certificate was seen for the documented consultation.', 'request_text' => 'Requested for submission to an employer, except for medico-legal purposes.', 'remarks' => 'Fictional example only.', 'signatory_name' => 'Dr. Fictional Example', 'signatory_title' => 'Municipal Health Officer', 'license_number' => '1234567', 'submission_token' => (string) Str::uuid(), 'confirm' => '1'], $overrides);
    }

    public function test_doctor_can_prepare_both_templates_and_reprint_saved_text_without_changing_history(): void
    {
        $this->get(route('certificates.index', [$this->patient, $this->visit]))->assertOk()->assertSee('Consultation / treatment')->assertSee('Medical examination')->assertDontSee('physically and mentally fit');
        foreach (['consultation', 'examination'] as $template) {
            $data = $this->data(['template' => $template]);
            $this->post(route('certificates.store', [$this->patient, $this->visit]), $data)->assertSessionHasNoErrors()->assertRedirect();
            $record = MedicalCertificate::latest('id')->firstOrFail();
            $this->assertSame($this->visit->id, $record->visit_id);
            $this->assertSame($this->doctor->id, $record->created_by);
            $this->assertSame($this->doctor->name, $record->content['signatory_name']);
            $this->post(route('certificates.store', [$this->patient, $this->visit]), $data)->assertRedirect(route('certificates.print', [$this->patient, $this->visit, $record]));
            $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()->assertSee($data['body'])->assertSee('NOT VALID WITHOUT SEAL')->assertSee('1234567');
        }
        $this->assertDatabaseCount('medical_certificates', 2);
        $this->assertSame(2, Activity::where('description', 'certificate.prepared')->count());
        $this->assertStringNotContainsString('1234567', Activity::all()->toJson());
        $record = MedicalCertificate::firstOrFail();
        $this->patient->update(['first_name' => 'Changed']);
        $this->visit->status = 'COMPLETED';
        $this->visit->save();
        $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()->assertSee('Fictional Certificate was seen');
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data())->assertSessionHasErrors('visit');
        $this->get(route('certificates.index', [$this->patient, $this->visit, 'copy' => $record->id]))->assertOk();
    }

    public function test_saved_certificate_offers_print_and_separate_pdf_download(): void
    {
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['template' => 'examination']))->assertRedirect();
        $record = MedicalCertificate::firstOrFail();
        $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()
            ->assertSee('onclick="window.print()"', false)->assertSee(route('certificates.pdf', [$this->patient, $this->visit, $record]));
        $response = $this->get(route('certificates.pdf', [$this->patient, $this->visit, $record]))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename=medical-certificate-'.$record->id.'.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
        $log = Activity::where('description', 'certificate.pdf_downloaded')->sole();
        $this->assertSame($record->id, $log->subject_id);
        $this->assertStringNotContainsString('1234567', $log->toJson());
    }

    public function test_unauthorized_roles_and_cross_patient_or_visit_requests_are_rejected(): void
    {
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data())->assertRedirect();
        $record = MedicalCertificate::firstOrFail();
        foreach (['System Admin', 'Information Staff', 'Nurse', 'Pharmacist', 'MedTech', 'Midwife'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('certificates.index', [$this->patient, $this->visit]))->assertForbidden();
            $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data())->assertForbidden();
            $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertForbidden();
            $this->get(route('certificates.pdf', [$this->patient, $this->visit, $record]))->assertForbidden();
        }
        $this->actingAs($this->doctor);
        $other = Patient::forceCreate(['created_by' => $this->doctor->id, 'duplicate_key' => (string) Str::uuid(), 'first_name' => 'Other', 'last_name' => 'Fictional']);
        $this->get(route('certificates.index', [$other, $this->visit]))->assertNotFound();
        $this->post(route('certificates.store', [$other, $this->visit]), $this->data())->assertNotFound();
        $this->get(route('certificates.print', [$other, $this->visit, $record]))->assertNotFound();
        $visit = Visit::forceCreate(['patient_id' => $other->id, 'service_id' => $this->visit->service_id, 'created_by' => $this->doctor->id, 'visit_date' => '2026-10-24']);
        $this->get(route('certificates.print', [$other, $visit, $record]))->assertNotFound();
        $this->get(route('certificates.pdf', [$other, $this->visit, $record]))->assertNotFound();
        $this->get(route('certificates.pdf', [$other, $visit, $record]))->assertNotFound();
        $this->get(route('certificates.index', [$other, $visit, 'copy' => $record->id]))->assertNotFound();
        $this->assertDatabaseCount('medical_certificates', 1);
    }

    public function test_new_copies_use_current_account_but_reprints_keep_the_original_name(): void
    {
        $originalName = $this->doctor->name;
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data())->assertSessionHasNoErrors();
        $record = MedicalCertificate::firstOrFail();
        $otherDoctor = User::factory()->create(['name' => 'Fictional Second Doctor']);
        $otherDoctor->assignRole('Doctor / Medical Officer');
        $this->actingAs($otherDoctor);
        $this->get(route('certificates.index', [$this->patient, $this->visit, 'copy' => $record->id]))->assertOk()->assertSee('value="Fictional Second Doctor"', false);
        $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()->assertSee($originalName);
        $data = $this->data();
        unset($data['signatory_name']);
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $data)->assertSessionHasNoErrors();
        $this->assertSame($otherDoctor->name, MedicalCertificate::latest('id')->firstOrFail()->content['signatory_name']);
        $this->doctor->update(['name' => 'Changed Account Name']);
        $this->assertSame($originalName, $record->fresh()->content['signatory_name']);
    }

    public function test_validation_and_print_escaping(): void
    {
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['body' => 'Replace ______', 'signatory_name' => '', 'license_number' => '', 'confirm' => false, 'patient_id' => 1]))->assertSessionHasErrors(['body', 'license_number', 'confirm', 'patient_id']);
        foreach (['ABC1234', '123 456', '12-345', str_repeat('1', 21)] as $license) {
            $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['license_number' => $license]))->assertSessionHasErrors('license_number');
        }
        $this->assertDatabaseCount('medical_certificates', 0);
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['body' => '<script>alert(1)</script>', 'template' => 'examination', 'blood_pressure' => '120/80']))->assertRedirect();
        $record = MedicalCertificate::firstOrFail();
        $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false)->assertDontSee('120/80')->assertDontSee('Examination findings:');
    }

    public function test_examination_measurements_come_from_this_visit_and_saved_copies_keep_the_snapshot(): void
    {
        $this->get(route('certificates.index', [$this->patient, $this->visit]))->assertOk()->assertDontSee('Examination findings');
        $assessment = VitalSign::forceCreate([
            'visit_id' => $this->visit->id, 'recorded_by' => $this->doctor->id, 'updated_by' => $this->doctor->id,
            'recorded_at' => now(), 'systolic_bp' => 120, 'diastolic_bp' => 80,
            'temperature' => 37.5,
        ]);
        $this->get(route('certificates.index', [$this->patient, $this->visit]))->assertOk()->assertSee('120/80')->assertDontSee('Pulse rate (/minute)');
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data([
            'template' => 'examination', 'blood_pressure' => '999/999', 'pulse_rate' => '999',
        ]))->assertSessionHasNoErrors();
        $record = MedicalCertificate::latest('id')->firstOrFail();
        $this->assertSame('120/80', $record->content['blood_pressure']);
        $this->assertSame('37.50', $record->content['temperature']);
        $this->assertArrayNotHasKey('pulse_rate', $record->content);
        $assessment->update(['systolic_bp' => 130]);
        $this->get(route('certificates.print', [$this->patient, $this->visit, $record]))->assertOk()->assertSee('120/80')->assertDontSee('Pulse Rate:')->assertDontSee('Respiratory Rate:');
        $this->get(route('certificates.index', [$this->patient, $this->visit, 'copy' => $record->id]))->assertOk()->assertSee('130/80');
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['template' => 'examination']))->assertSessionHasNoErrors();
        $this->assertSame('130/80', MedicalCertificate::latest('id')->firstOrFail()->content['blood_pressure']);
        $this->post(route('certificates.store', [$this->patient, $this->visit]), $this->data(['blood_pressure' => '999/999']))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('blood_pressure', MedicalCertificate::latest('id')->firstOrFail()->content);
    }
}
