<?php

namespace Database\Seeders;

use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Activitylog\Models\Activity;

class CompleteHistoricalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Historical demo completion is local/testing only.');
        }
        $changed = 0;
        DB::transaction(function () use (&$changed) {
            $visits = Visit::where('visit_number', 'like', 'HISTORY2026-VISIT-%')
                ->where('status', 'OPEN')->whereNull('completed_at')
                ->whereDate('visit_date', '<', now('Asia/Manila')->toDateString())
                ->whereHas('patient')->with(['patient', 'vitalSign', 'treatmentRecord'])->lockForUpdate()->get();
            foreach ($visits as $visit) {
                $patient = $visit->patient;
                $itr = $visit->treatmentRecord;
                $vitals = $visit->vitalSign;
                $seeded = Activity::where('subject_type', $visit->getMorphClass())->where('subject_id', $visit->id)
                    ->where('description', 'demo.workflow_seeded')->get()
                    ->contains(fn ($event) => $event->properties->get('source') === 'HistoricalDemoSeeder');
                if (! $seeded || ! str_starts_with($patient->patient_number, 'HISTORY2026-PATIENT-')
                    || $patient->lock_version > 1 || ! $patient->updated_at->equalTo($patient->created_at)
                    || ! $visit->updated_at->equalTo($visit->created_at)
                    || ! $itr || ! $vitals || $itr->lock_version !== 1 || $vitals->lock_version !== 1
                    || ! $itr->updated_at->equalTo($itr->created_at) || ! $vitals->updated_at->equalTo($vitals->created_at)
                    || $visit->prescription()->exists() || $visit->laboratoryRecords()->exists()
                    || $visit->dispositionRecords()->exists() || $visit->eligibilityCheck()->exists()) {
                    continue;
                }
                $completed = $itr->created_at->copy()->addMinutes(20);
                if ($completed->toDateString() !== $visit->visit_date->toDateString()) {
                    continue;
                }
                $visit->forceFill(['status' => 'COMPLETED', 'completed_at' => $completed, 'updated_at' => $completed])->save();
                activity('demo')->performedOn($visit)->withProperties([
                    'source' => 'CompleteHistoricalDemoSeeder', 'status' => 'COMPLETED',
                ])->log('demo.workflow_seeded');
                $changed++;
            }
        });
        $this->command?->info("Completed {$changed} untouched past historical demo visits. Current/future and edited records were preserved.");
    }
}
