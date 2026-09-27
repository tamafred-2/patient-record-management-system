<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class WeekThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
    }

    private function asRole(string $email): User
    {
        $user = User::where('email', $email.'@rhu.test')->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    public function test_demo_is_repeatable_and_production_rejected(): void
    {
        $this->assertDatabaseCount('patients', 3);
        $this->assertDatabaseCount('visits', 3);
        $patient = Patient::first();
        $patient->first_name = 'Edited fictional profile';
        $patient->save();
        $this->seed(WorkflowDemoSeeder::class);
        $this->assertDatabaseCount('patients', 3);
        $this->assertSame('Edited fictional profile', $patient->fresh()->first_name);
        $this->app->instance('env', 'production');
        $this->expectException(LogicException::class);
        $this->seed(WorkflowDemoSeeder::class);
    }

    public function test_history_is_role_scoped_and_admin_denied(): void
    {
        $patient = Patient::first();
        $url = route('history.show', $patient);
        $this->asRole('doctor');
        $this->get($url)->assertOk()->assertSee('ITR recorded')->assertSee('Medicine release recorded')->assertSee('Medical certificate requested');
        $this->asRole('information');
        $this->get($url)->assertOk()->assertSee('Visit registered')->assertDontSee('ITR recorded')->assertDontSee('Medicine release recorded');
        foreach (['admin', 'nurse', 'pharmacist', 'medtech', 'midwife'] as $role) {
            $this->asRole($role);
            $this->get($url)->assertForbidden();
        }
        $this->asRole('doctor');
        $patient->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_supporting_records_scoping_validation_and_duplicate_protection(): void
    {
        $this->asRole('doctor');
        $visit = Visit::first();
        $patient = $visit->patient;
        $url = route('dispositions.show', [$patient, $visit]);
        $data = ['type' => 'ADVISED_HIGHER_FACILITY', 'reason' => 'Fictional advice', 'submission_token' => (string) Str::uuid(), 'confirm' => 1];
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasErrors('submission_token');
        $this->get($url)->assertOk()->assertSee('Advice to seek a higher facility');
        $wrong = route('dispositions.show', [Patient::where('id', '!=', $patient->id)->first(), $visit]);
        $this->get($wrong)->assertNotFound();
        $this->post($wrong, $data)->assertNotFound();
        $this->post($url, array_replace($data, ['type' => 'CERTIFICATE_REQUEST', 'facility_name' => 'Not allowed']))->assertSessionHasErrors('facility_name');
        $this->post($url, array_replace($data, ['issued_at' => '2026-01-01']))->assertSessionHasErrors('issued_at');
        $this->post($url, array_replace($data, ['reason' => '']))->assertSessionHasErrors('reason');
        foreach (['admin', 'information', 'nurse', 'pharmacist', 'medtech', 'midwife'] as $role) {
            $this->asRole($role);
            $this->get($url)->assertForbidden();
            $this->post($url, $data)->assertForbidden();
        }
        $this->asRole('doctor');
        $visit->status = 'COMPLETED';
        $visit->save();
        $this->post($url, array_replace($data, ['submission_token' => (string) Str::uuid()]))->assertSessionHasErrors('visit');
    }

    public function test_reports_aggregate_and_exports_require_permission(): void
    {
        $this->asRole('admin');
        $from = now('Asia/Manila')->subDay()->toDateString();
        $to = now('Asia/Manila')->toDateString();
        $query = http_build_query(compact('from', 'to'));
        $this->get('/reports?'.$query)->assertOk()->assertSee('3 visits')->assertSee('3 distinct patients')->assertDontSee('Fictional Aster');
        $pdf = $this->get('/reports/pdf?'.$query)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->get('/reports?from=2026-02-02&to=2026-01-01')->assertSessionHasErrors();
        $this->get('/reports?from=2020-01-01&to=2026-01-01')->assertSessionHasErrors();
        foreach (['doctor', 'information', 'nurse', 'pharmacist', 'medtech', 'midwife'] as $role) {
            $this->asRole($role);
            $this->get('/reports')->assertForbidden();
            $this->get('/reports/pdf')->assertForbidden();
        }
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('reports.view');
        $this->actingAs($viewer);
        $this->get('/reports')->assertOk();
        $this->get('/reports/pdf')->assertForbidden();
        $this->assertTrue(Activity::where('description', 'report.exported')->exists());
    }

    public function test_clinical_pdf_is_scoped_private_and_separately_authorized(): void
    {
        $visit = Visit::first();
        $patient = $visit->patient;
        $url = route('visits.pdf', [$patient, $visit]);
        $this->asRole('doctor');
        $response = $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->get(route('visits.pdf', [Patient::where('id', '!=', $patient->id)->first(), $visit]))->assertNotFound();
        foreach (['admin', 'information', 'nurse', 'pharmacist', 'medtech', 'midwife'] as $role) {
            $this->asRole($role);
            $this->get($url)->assertForbidden();
        }
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('consultations.view');
        $this->actingAs($viewer);
        $this->get($url)->assertForbidden();
        $this->assertTrue(Activity::where('description', 'visit.summary_exported')->exists());
    }

    public function test_dashboard_modules_follow_roles(): void
    {
        $this->asRole('doctor');
        $this->get('/dashboard')->assertOk()->assertSee('Doctor visits')->assertDontSee('User Accounts')->assertDontSee('Generate report');
        $this->asRole('pharmacist');
        $this->get('/dashboard')->assertOk()->assertSee('Record medicine releases')->assertDontSee('Doctor visits');
        $this->asRole('admin');
        $this->get('/dashboard')->assertOk()->assertSee('Reports')->assertSee('User Accounts')->assertDontSee('Doctor visits');
        $this->asRole('midwife');
        $this->get('/dashboard')->assertOk()->assertSee('Midwife care')->assertSee('Vaccination');
    }
}
