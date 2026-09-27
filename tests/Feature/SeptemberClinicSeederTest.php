<?php

namespace Tests\Feature;

use App\Models\LaboratoryRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentRecord;
use App\Models\Visit;
use Database\Seeders\SeptemberClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeptemberClinicSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_dataset_has_daily_volume_dates_completion_and_linked_records(): void
    {
        $this->seed(SeptemberClinicSeeder::class);
        $daily = Visit::all()->groupBy(fn ($visit) => $visit->visit_date->toDateString());
        $this->assertCount(26, $daily);
        $this->assertSame('2026-08-31', $daily->keys()->min());
        $this->assertSame('2026-09-25', $daily->keys()->max());
        $prefixes = [];
        foreach ($daily as $date => $visits) {
            $dayPrefixes = $visits->map(fn ($visit) => substr($visit->queue_reference, 0, 2))->unique();
            $this->assertCount(1, $dayPrefixes);
            $prefixes[] = $dayPrefixes->first();
            $this->assertGreaterThanOrEqual(20, $visits->count());
            $this->assertLessThanOrEqual(50, $visits->count());
            $this->assertSame($visits->count(), $visits->pluck('patient_id')->unique()->count());
            foreach ($visits as $visit) {
                $completed = $date <= '2026-09-23';
                $this->assertSame($completed ? 'COMPLETED' : 'OPEN', $visit->status);
                $this->assertSame($completed, $visit->completed_at !== null);
                $this->assertTrue($visit->patient->created_at->lte($visit->created_at));
                if ($completed) {
                    $this->assertTrue($visit->completed_at->gte($visit->created_at));
                }
            }
        }
        $this->assertCount(26, array_unique($prefixes));
        $this->assertGreaterThan(5, $daily->map->count()->unique()->count());
        $this->assertSame(0, Patient::where('first_name', 'like', '%Demo%')->count());
        $this->assertSame(0, TreatmentRecord::whereNull('fever')->count());
        $this->assertGreaterThan(0, LaboratoryRecord::count());
        foreach (Prescription::with(['items', 'dispensings.items'])->get() as $rx) {
            $this->assertSame($rx->visit_id, TreatmentRecord::findOrFail($rx->treatment_record_id)->visit_id);
            foreach ($rx->items as $item) {
                $released = DB::table('dispensing_items')->where('prescription_item_id', $item->id)->sum('quantity_dispensed');
                $this->assertLessThanOrEqual($item->quantity_prescribed, $released);
            }
        }
        $this->assertSame(0, Visit::whereNull('queue_reference')->count());
        $missing = Visit::firstOrFail();
        $entered = Visit::where('id', '!=', $missing->id)->firstOrFail();
        DB::table('visits')->where('id', $missing->id)->update(['queue_reference' => null]);
        DB::table('visits')->where('id', $entered->id)->update(['queue_reference' => 'EXTERNAL-778']);
        $originalStatus = $missing->status;
        $originalTime = $missing->updated_at->toDateTimeString();
        $legacy = Visit::whereNotIn('id', [$missing->id, $entered->id])->firstOrFail();
        $legacyNumber = (int) substr($legacy->visit_number, -3);
        DB::table('visits')->where('id', $legacy->id)->update(['queue_reference' => sprintf('Q-%03d', $legacyNumber)]);
        $oldRandom = Visit::whereNotIn('id', [$missing->id, $entered->id, $legacy->id])->firstOrFail();
        $slot = (int) substr($oldRandom->visit_number, -3) - 1;
        $pick = fn ($key) => hexdec(substr(hash('sha256', 'september-clinic-v1-'.$key), 0, 7)) % 26;
        DB::table('visits')->where('id', $oldRandom->id)->update(['queue_reference' => sprintf('%s%s-%03d', chr(65 + $pick('queue-first-0-'.$slot)), chr(65 + $pick('queue-second-0-'.$slot)), $slot + 1)]);
        $patient = Patient::firstOrFail();
        $patient->update(['first_name' => 'Edited']);
        $before = Visit::count();
        $this->seed(SeptemberClinicSeeder::class);
        $this->assertSame($before, Visit::count());
        $this->assertMatchesRegularExpression('/^[A-Z]{2}-001$/', $missing->fresh()->queue_reference);
        $this->assertMatchesRegularExpression('/^[A-Z]{2}-[0-9]{3}$/', $legacy->fresh()->queue_reference);
        $this->assertSame(substr($missing->fresh()->queue_reference, 0, 2), substr($oldRandom->fresh()->queue_reference, 0, 2));
        $this->assertSame('EXTERNAL-778', $entered->fresh()->queue_reference);
        $this->assertSame($originalStatus, $missing->fresh()->status);
        $this->assertSame($originalTime, $missing->fresh()->updated_at->toDateTimeString());
        $this->assertSame('Edited', $patient->fresh()->first_name);
    }

    public function test_production_cannot_seed(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\LogicException::class);
        $this->seed(SeptemberClinicSeeder::class);
    }
}
