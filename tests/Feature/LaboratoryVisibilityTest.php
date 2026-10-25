<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LaboratoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_medtech_sees_only_laboratory_work_and_consultations_appear_after_an_order(): void
    {
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $medtech = User::where('email', 'medtech@rhu.test')->firstOrFail();
        $nurse = User::where('email', 'nurse@rhu.test')->firstOrFail();
        $doctor = User::where('email', 'doctor@rhu.test')->firstOrFail();
        $source = Visit::firstOrFail();
        $visits = [];
        foreach (['CONSULTATION', 'LABORATORY', 'VACCINATION'] as $service) {
            $visit = $source->replicate();
            $visit->service_id = Service::where('seed_key', $service)->firstOrFail()->id;
            $visit->visit_number = 'VISIBILITY-'.$service;
            $visit->queue_reference = 'VISIBILITY-'.$service;
            $visit->visit_date = now('Asia/Manila')->toDateString();
            $visit->status = 'OPEN';
            $visit->save();
            $visits[$service] = $visit;
        }
        $consult = $visits['CONSULTATION'];
        $direct = $visits['LABORATORY'];
        $patient = $consult->patient;
        $this->actingAs($medtech);
        foreach (['/laboratory?all=1&search=VISIBILITY', '/laboratory?search=VISIBILITY'] as $url) {
            $this->get($url)->assertOk()->assertSee($direct->visit_number)->assertDontSee($consult->visit_number)->assertDontSee('VISIBILITY-VACCINATION');
        }
        $this->get('/dashboard?search=VISIBILITY')->assertOk()->assertViewHas('departmentTotals', fn ($totals) => $totals['laboratory'] === 1);
        $this->getJson('/dashboard/visits?section=laboratory&search=VISIBILITY')->assertOk()->assertJsonPath('count', 1)
            ->assertSee($direct->visit_number)->assertDontSee($consult->visit_number);
        $this->get(route('laboratory.show', [$patient, $consult]))->assertNotFound();
        $before = LaboratoryRecord::count();
        $this->post(route('laboratory.store', [$patient, $consult]), [
            'test_name' => 'Urinalysis', 'availability_status' => 'PENDING', 'lock_version' => 0, 'submission_token' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertSame($before, LaboratoryRecord::count());
        $this->get(route('laboratory.show', [$patient, $direct]))->assertOk();

        $this->actingAs($nurse)->put(route('assessments.save', [$patient, $consult]), ['lock_version' => 0, 'temperature' => 37, 'assessment_summary' => 'Fictional laboratory routing assessment.'])->assertSessionHasNoErrors();
        $this->actingAs($doctor)->post(route('followups.request', [$patient, $consult]), [
            'test_names' => ['Urinalysis'], 'request_reason' => 'Fictional laboratory request.',
            'assessment_version' => $consult->fresh()->vitalSign->lock_version, 'submission_token' => (string) Str::uuid(), 'confirm' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($medtech)->get('/laboratory?all=1&search=VISIBILITY')->assertOk()->assertSee($consult->visit_number)->assertSee($direct->visit_number)->assertDontSee('VISIBILITY-VACCINATION');
        $this->getJson('/dashboard/visits?section=laboratory&search=VISIBILITY')->assertJsonPath('count', 2);
        $this->get(route('laboratory.show', [$patient, $consult]))->assertOk()->assertSee('Urinalysis');
        $record = $consult->laboratoryRecords()->sole();
        $record->availability_status = 'EXTERNAL_ADVISED';
        $record->external_advice = 'Fictional external advice.';
        $record->save();
        $consult->status = 'COMPLETED';
        $consult->save();
        $this->get('/laboratory?all=1&record=with_records&search=VISIBILITY')->assertOk()->assertSee($consult->visit_number)->assertDontSee($direct->visit_number);
        $this->get(route('laboratory.edit', [$patient, $consult, $record]))->assertOk();
        $this->get(route('laboratory.edit', [$patient, $direct, $record]))->assertNotFound();
        $this->actingAs($nurse)->get('/laboratory?all=1')->assertForbidden();
    }
}
