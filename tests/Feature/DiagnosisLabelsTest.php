<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Services\DiagnosisLabels;
use Database\Seeders\AnalyticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosisLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuse_is_normalized_scoped_and_does_not_rewrite_records(): void
    {
        $this->withoutVite();
        $this->seed(AnalyticsDemoSeeder::class);
        $record = TreatmentRecord::orderBy('id')->firstOrFail();
        $record->diagnoses = [' FICTIONAL   condition Alpha ', 'fictional condition alpha'];
        $record->save();
        $before = $record->diagnoses;
        $this->assertSame(['fictional condition alpha', 'fictional condition beta', 'fictional condition gamma'], app(DiagnosisLabels::class)->suggestions());
        $this->assertSame($before, $record->fresh()->diagnoses);
        $patient = Patient::where('patient_number', 'ANALYTICS-PATIENT-Gamma')->firstOrFail();
        $patient->delete();
        $this->assertSame(['fictional condition alpha'], app(DiagnosisLabels::class)->suggestions());
        $visit = Patient::where('patient_number', 'ANALYTICS-PATIENT-Alpha')->firstOrFail()->visits()->firstOrFail();
        $url = route('itr.edit', [$visit->patient, $visit]);
        $this->actingAs(User::where('email', 'doctor@rhu.test')->firstOrFail())->get($url)->assertOk()->assertSee('Previously recorded diagnosis')->assertViewHas('diagnosisSuggestions', ['fictional condition alpha']);
        foreach (['admin', 'nurse', 'pharmacist', 'information', 'medtech', 'midwife'] as $role) {
            $this->actingAs(User::where('email', $role.'@rhu.test')->firstOrFail())->get($url)->assertForbidden()->assertDontSee('fictional condition alpha');
        }
    }
}
