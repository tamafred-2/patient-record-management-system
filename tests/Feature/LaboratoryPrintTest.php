<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visit;
use App\Support\PrintableDocuments;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LaboratoryPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_results_print_only_for_lab_staff_with_correct_patient_visit_and_audit_privacy(): void
    {
        $this->withoutVite();
        $this->seed(WorkflowDemoSeeder::class);
        $visit = Visit::firstOrFail();
        $record = $visit->laboratoryRecords()->firstOrFail();
        $url = route('prints.laboratory-result', [$visit->patient, $visit, $record]);
        $medtech = User::where('email', 'medtech@rhu.test')->sole();
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($medtech)->get($url)->assertNotFound();
        $record->forceFill([
            'availability_status' => 'PERFORMED_RHU', 'completed_at' => now(), 'updated_by' => $medtech->id,
            'result_rows' => [['measurement' => 'Fictional component', 'value' => '<script>fictional</script>', 'unit' => 'example unit']],
        ])->save();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Fictional component')
            ->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false)->assertDontSee('Fictional assessment');
        $this->get(route('laboratory.show', [$visit->patient, $visit]))->assertSee($url, false);
        $this->assertContains($url, array_column(PrintableDocuments::forVisit($medtech, $visit->fresh()), 'url'));
        $audit = Activity::where('description', 'laboratory.result_print_viewed')->sole();
        $this->assertSame(['visit_id', 'version'], array_keys($audit->properties->all()));
        foreach (['doctor', 'nurse', 'admin', 'information', 'pharmacist', 'midwife'] as $role) {
            $user = User::where('email', $role.'@rhu.test')->sole();
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->assertNotContains($url, array_column(PrintableDocuments::forVisit($user, $visit->fresh()), 'url'));
        }
        $this->actingAs($medtech);
        $other = Visit::where('id', '!=', $visit->id)->firstOrFail();
        $this->get(route('prints.laboratory-result', [$other->patient, $visit, $record]))->assertNotFound();
        $this->get(route('prints.laboratory-result', [$other->patient, $other, $record]))->assertNotFound();
        $visit->forceFill(['status' => 'COMPLETED'])->save();
        $this->get($url)->assertOk();
        $record->forceFill(['result_rows' => null, 'result' => 'Fictional legacy result'])->save();
        $this->get($url)->assertOk()->assertSee('Fictional legacy result');
        $record->forceFill(['result' => null])->save();
        $this->get($url)->assertNotFound();
        $record->forceFill(['result' => 'Fictional result', 'availability_status' => 'EXTERNAL_ADVISED'])->save();
        $this->get($url)->assertNotFound();
        $visit->patient->delete();
        $this->get($url)->assertNotFound();
    }
}
