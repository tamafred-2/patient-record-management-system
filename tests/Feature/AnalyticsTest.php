<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Database\Seeders\AnalyticsDemoSeeder;
use Database\Seeders\ExampleRecordsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(ExampleRecordsSeeder::class);
    }

    private function loginAs(string $role): void
    {
        $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail());
    }

    public function test_admin_counts_dates_repeat_patients_missing_answers_and_privacy(): void
    {
        $this->loginAs('admin');
        $today = now('Asia/Manila')->toDateString();
        $response = $this->get('/analytics?from='.$today.'&to='.$today)->assertOk();
        $panels = $response->viewData('panels');
        $this->assertSame(8, $panels['Patient trends']['Visits']);
        $this->assertSame(8, $panels['Patient trends']['Distinct patients']);
        $this->assertSame(1, $panels['Patient trends']['Patients seen before period']);
        $this->assertSame(7, $panels['Patient trends']['First recorded visit in period']);
        $this->assertSame([$today => 8], $response->viewData('trend'));
        $this->assertSame(1, $panels['Laboratory activity']['Performed at RHU']);
        $this->assertSame(1, $panels['Laboratory activity']['External laboratory advised']);
        $this->assertSame(1, $panels['Prescription activity']['Awaiting first release']);
        $this->assertSame(3, $panels['Prescription activity']['With at least one release']);
        $fever = collect($response->viewData('screening'))->firstWhere('label', 'Fever');
        $this->assertSame(['label' => 'Fever', 'yes' => 6, 'no' => 0, 'unanswered' => 1], $fever);
        $response->assertDontSee('Fictional Released')->assertDontSee('DEMO-PATIENT')->assertDontSee('Fictional demonstration medicine');
        Patient::where('patient_number', 'EXAMPLE-PATIENT-LABORATORY')->firstOrFail()->delete();
        $response = $this->get('/analytics?from='.$today.'&to='.$today)->assertOk();
        $this->assertSame(7, $response->viewData('panels')['Patient trends']['Visits']);
        $this->assertSame(0, $response->viewData('panels')['Laboratory activity']['Performed at RHU']);
    }

    public function test_departments_cannot_request_other_sections_and_combined_roles_are_additive(): void
    {
        $allowed = [
            'information' => ['Patient trends', 'Service demand'],
            'nurse' => ['Initial assessment'],
            'doctor' => ['Consultation records', 'Supporting records', 'Prescription activity'],
            'medtech' => ['Laboratory activity'],
            'pharmacist' => ['Prescription activity'],
        ];
        foreach ($allowed as $role => $sections) {
            $this->loginAs($role);
            $response = $this->get('/analytics?overview=1&role=admin')->assertOk();
            $this->assertEqualsCanonicalizing($sections, array_keys($response->viewData('panels')));
            $this->assertFalse($response->viewData('overview'));
            if ($role !== 'doctor') {
                $this->assertNull($response->viewData('screening'));
                $response->assertDontSee('ITR screening responses');
            }
            if ($role === 'pharmacist') {
                $response->assertDontSee('Draft prescriptions');
            }
        }
        $nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
        $nurse->assignRole('MedTech');
        $this->actingAs($nurse);
        $this->assertEqualsCanonicalizing(['Initial assessment', 'Laboratory activity'], array_keys($this->get('/analytics')->viewData('panels')));
    }

    public function test_access_range_validation_empty_dates_and_guests(): void
    {
        $this->get('/analytics')->assertRedirect('/login');
        $this->loginAs('midwife');
        $this->get('/analytics')->assertForbidden();
        $this->loginAs('admin');
        foreach (['from=2026-02-31', 'from=2026-09-25&to=2026-09-01', 'from=2020-01-01&to=2026-01-01'] as $query) {
            $this->get('/analytics?'.$query)->assertSessionHasErrors();
        }
        $response = $this->get('/analytics?from=2001-01-01&to=2001-01-03')->assertOk();
        $this->assertSame(['2001-01-01' => 0, '2001-01-02' => 0, '2001-01-03' => 0], $response->viewData('trend'));
        $this->assertSame(0, $response->viewData('panels')['Patient trends']['Visits']);
        $admin = User::where('email', 'admin@rhu.test')->firstOrFail();
        $admin->is_active = false;
        $admin->save();
        $this->actingAs($admin)->get('/analytics')->assertRedirect('/login');
    }

    public function test_diagnosis_ranking_deduplicates_labels_and_patients_and_seeder_is_repeatable(): void
    {
        $this->seed(AnalyticsDemoSeeder::class);
        $this->seed(AnalyticsDemoSeeder::class);
        $this->assertDatabaseCount('patients', 12);
        $this->assertDatabaseCount('visits', 19);
        $this->loginAs('admin');
        $from = now('Asia/Manila')->subDays(14)->toDateString();
        $to = now('Asia/Manila')->toDateString();
        $response = $this->get('/analytics?from='.$from.'&to='.$to)->assertOk();
        $this->assertSame(['label' => 'fictional condition alpha', 'visits' => 6, 'patients' => 2], $response->viewData('diseases')[0]);
        $this->assertSame(9, $response->viewData('diagnosisCoverage')['with']);
        $this->loginAs('pharmacist');
        $response = $this->get('/analytics')->assertOk();
        $this->assertNull($response->viewData('diseases'));
        $response->assertDontSee('fictional condition alpha');
        $this->app->instance('env', 'production');
        $this->expectException(\LogicException::class);
        $this->seed(AnalyticsDemoSeeder::class);
    }
}
