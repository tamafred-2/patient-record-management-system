<?php

namespace Tests\Feature;

use App\Models\MidwifeCareRecord;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Notifications\WorkflowAlert;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MidwifeRoleTest extends TestCase
{
    use RefreshDatabase;

    private Visit $visit;

    private Visit $consultation;

    private User $midwife;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $this->visit = Visit::orderBy('id')->skip(1)->firstOrFail();
        $this->visit->forceFill(['service_id' => Service::where('seed_key', 'MIDWIFE_CARE')->firstOrFail()->id, 'status' => 'OPEN'])->save();
        $this->consultation = Visit::where('id', '!=', $this->visit->id)->whereHas('service', fn ($service) => $service->where('seed_key', '!=', 'MIDWIFE_CARE'))->firstOrFail();
        $this->midwife = User::where('email', 'midwife@rhu.test')->firstOrFail();
    }

    private function vitals(Visit $visit): array
    {
        return ['lock_version' => $visit->fresh()->vitalSign?->lock_version ?? 0, 'systolic_bp' => 110, 'diastolic_bp' => 70, 'weight_kg' => 58.5, 'assessment_summary' => 'Fictional prenatal assessment.'];
    }

    private function care(array $extra = []): array
    {
        return array_merge(['care_type' => 'PRENATAL', 'purpose' => 'Fictional prenatal check-up', 'confirm' => 1, 'lock_version' => 0, 'submission_token' => (string) Str::uuid()], $extra);
    }

    public function test_midwife_records_vital_signs_only_for_midwife_service_visits(): void
    {
        VitalSign::where('visit_id', $this->visit->id)->delete();
        $this->actingAs($this->midwife)->get(route('midwife-care.show', [$this->visit->patient, $this->visit]))->assertOk()
            ->assertSee('Vital signs and assessment')->assertSee(route('assessments.edit', [$this->visit->patient, $this->visit]), false);
        $this->get(route('assessments.edit', [$this->visit->patient, $this->visit]))->assertOk()->assertSee('Back to Midwife care record')->assertSee('Save vital signs');
        $this->put(route('assessments.save', [$this->visit->patient, $this->visit]), $this->vitals($this->visit))->assertSessionHasNoErrors();
        $this->assertSame($this->midwife->id, $this->visit->fresh()->vitalSign->recorded_by);

        $this->get(route('assessments.edit', [$this->consultation->patient, $this->consultation]))->assertForbidden();
        $this->put(route('assessments.save', [$this->consultation->patient, $this->consultation]), $this->vitals($this->consultation))->assertForbidden();
        $this->get(route('assessments.index'))->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertDontSee('Initial assessment');
    }

    public function test_new_midwife_visit_alerts_midwives_and_opens_their_page(): void
    {
        $staff = User::where('email', 'information@rhu.test')->firstOrFail();
        $this->actingAs($staff)->post(route('patients.visits.store', $this->visit->patient), ['service_id' => $this->visit->service_id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertSessionHasNoErrors();
        $created = Visit::latest('id')->firstOrFail();
        $alert = $this->midwife->notifications()->where('type', WorkflowAlert::class)->sole();
        $this->assertSame('visit_waiting', $alert->data['kind']);
        $this->actingAs($this->midwife)->get(route('notifications.open', $alert->id))->assertRedirect(route('midwife-care.show', [$created->patient, $created]));

        $this->actingAs($staff)->post(route('patients.visits.store', $this->visit->patient), ['service_id' => $this->consultation->service_id, 'visit_date' => now('Asia/Manila')->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->midwife->notifications()->count(), 'Consultation visits do not alert midwives.');
    }

    public function test_prenatal_fields_save_with_calculated_edd_and_age_of_gestation(): void
    {
        $this->actingAs($this->midwife);
        $url = route('midwife-care.store', [$this->visit->patient, $this->visit]);
        $lmp = $this->visit->visit_date->copy()->subDays(70)->toDateString();
        $this->post($url, $this->care(['lmp' => $lmp, 'gravida' => 2, 'para' => 1, 'fundal_height_cm' => '12.5', 'fetal_heart_tone' => 145]))->assertSessionHasNoErrors();
        $record = MidwifeCareRecord::latest('id')->firstOrFail();
        $this->assertSame([2, 1, 145], [$record->gravida, $record->para, $record->fetal_heart_tone]);
        $this->assertSame('10 weeks 0 days', $record->ageOfGestation());
        $this->assertSame($this->visit->visit_date->copy()->subDays(70)->addDays(280)->toDateString(), $record->expectedDelivery()->toDateString());
        $this->get(route('midwife-care.show', [$this->visit->patient, $this->visit]))->assertSee('10 weeks 0 days')->assertSee('G2 P1')->assertSee('145 bpm');

        $this->post($url, $this->care(['gravida' => 1, 'para' => 2]))->assertSessionHasErrors('para');
        $this->post($url, $this->care(['lmp' => $this->visit->visit_date->copy()->addDay()->toDateString()]))->assertSessionHasErrors('lmp');
        $this->post($url, $this->care(['lmp' => $this->visit->visit_date->copy()->subDays(320)->toDateString()]))->assertSessionHasErrors('lmp');
        $this->post($url, $this->care(['fetal_heart_tone' => 400, 'fundal_height_cm' => '12.55']))->assertSessionHasErrors(['fetal_heart_tone', 'fundal_height_cm']);
        $this->post($url, $this->care(['care_type' => 'FAMILY_PLANNING', 'gravida' => 1]))->assertSessionHasErrors('gravida');
        $this->assertSame(1, MidwifeCareRecord::count());
    }

    public function test_monthly_report_shows_counts_only_for_permitted_roles(): void
    {
        $this->actingAs($this->midwife)->post(route('midwife-care.store', [$this->visit->patient, $this->visit]), $this->care(['lmp' => $this->visit->visit_date->copy()->subDays(30)->toDateString()]))->assertSessionHasNoErrors();
        $month = $this->visit->visit_date->format('Y-m');
        $this->get(route('midwife-report.index', ['month' => $month]))->assertOk()
            ->assertSeeInOrder(['Prenatal follow-up', '1', '1'])->assertSee('Prenatal entries with LMP recorded: 1')
            ->assertDontSee($this->visit->patient->full_name)->assertDontSee('Fictional prenatal check-up');
        $this->get(route('midwife-report.print', ['month' => $month]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Midwife monthly report');
        $this->assertSame($month, Activity::where('description', 'midwife.report_printed')->sole()->properties['month']);
        $this->get(route('midwife-report.index', ['month' => now('Asia/Manila')->addMonth()->format('Y-m')]))->assertSessionHasErrors('month');
        $this->actingAs(User::where('email', 'admin@rhu.test')->firstOrFail())->get(route('midwife-report.index'))->assertOk();
        foreach (['pharmacist', 'nurse', 'medtech'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get(route('midwife-report.index'))->assertForbidden();
        }
    }

    public function test_report_counts_records_dated_on_the_last_day_of_the_month(): void
    {
        $this->travelTo(now('Asia/Manila')->startOfMonth()->subDay()->setTime(10, 0));
        $lastDay = now('Asia/Manila')->toDateString();
        $this->visit->forceFill(['visit_date' => $lastDay])->save();
        $this->actingAs($this->midwife)->post(route('midwife-care.store', [$this->visit->patient, $this->visit]), $this->care())->assertSessionHasNoErrors();
        $this->get(route('midwife-report.index', ['month' => substr($lastDay, 0, 7)]))->assertOk()->assertViewHas('careRows', fn ($rows) => $rows['PRENATAL']['entries'] === 1);
    }
}
