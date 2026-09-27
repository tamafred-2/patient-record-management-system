<?php

namespace Database\Seeders;

use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class RandomizeHistoricalDatesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Synthetic date variation is local/testing only.');
        }
        DB::transaction(function () {
            $start = CarbonImmutable::parse('2026-01-05', 'Asia/Manila');
            $end = CarbonImmutable::parse('2026-09-28', 'Asia/Manila');
            $span = (int) $start->diffInDays($end);
            foreach (Patient::where('patient_number', 'like', 'HISTORY2026-PATIENT-%')->lockForUpdate()->get() as $patient) {
                $index = (int) substr($patient->patient_number, -3) - 1;
                if ($index < 0 || $index >= 48) {
                    continue;
                }
                $oldStart = $start->addDays($index * 3)->setTime(8, ($index % 4) * 15);
                if ($patient->created_at->format('Y-m-d H:i:s') !== $oldStart->format('Y-m-d H:i:s') || $patient->updated_at->format('Y-m-d H:i:s') !== $oldStart->format('Y-m-d H:i:s') || $patient->lock_version > 1) {
                    continue;
                }
                $visits = $patient->visits()->with(['vitalSign', 'treatmentRecord'])->orderBy('visit_date')->lockForUpdate()->get();
                $safe = $visits->isNotEmpty();
                foreach ($visits as $visit) {
                    $safe = $safe && str_starts_with($visit->visit_number, 'HISTORY2026-VISIT-')
                        && $visit->status === 'OPEN' && $visit->updated_at->equalTo($visit->created_at)
                        && $visit->vitalSign && $visit->treatmentRecord
                        && $visit->vitalSign->lock_version === 1 && $visit->treatmentRecord->lock_version === 1
                        && $visit->vitalSign->updated_at->equalTo($visit->vitalSign->created_at)
                        && $visit->treatmentRecord->updated_at->equalTo($visit->treatmentRecord->created_at)
                        && ! $visit->eligibilityCheck()->exists() && ! $visit->laboratoryRecords()->exists()
                        && ! $visit->dispositionRecords()->exists() && ! $visit->prescription()->exists();
                }
                if (! $safe) {
                    continue;
                }
                // Hash-based pseudo-random offsets: varied, repeatable, no global RNG side effects.
                $firstOffset = $index === 0 ? 0 : $this->number($index.'-first', 0, 100);
                $offsets = [$firstOffset];
                if ($index === 0 && $visits->count() > 1) {
                    $offsets[] = $span;
                }
                $attempt = 0;
                while (count($offsets) < $visits->count()) {
                    $candidate = $this->number($index.'-visit-'.$attempt++, $firstOffset + 1, $span);
                    if (! in_array($candidate, $offsets)) {
                        $offsets[] = $candidate;
                    }
                }
                sort($offsets);
                foreach ($visits as $sequence => $visit) {
                    $date = $start->addDays($offsets[$sequence])->setTime(8 + $this->number($index.'-hour-'.$sequence, 0, 6), $this->number($index.'-minute-'.$sequence, 0, 11) * 5);
                    $visit->forceFill(['visit_date' => $date->toDateString(), 'created_at' => $date, 'updated_at' => $date])->save();
                    $visit->vitalSign->forceFill(['recorded_at' => $date->addMinutes(10), 'created_at' => $date, 'updated_at' => $date])->save();
                    $visit->treatmentRecord->forceFill(['created_at' => $date->addMinutes(25), 'updated_at' => $date->addMinutes(25)])->save();
                    if ($sequence === 0) {
                        $patient->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
                    }
                }
                activity('demo')->performedOn($patient)->withProperties(['source' => 'RandomizeHistoricalDatesSeeder'])->log('demo.workflow_seeded');
            }
        });
    }

    private function number(string $key, int $minimum, int $maximum): int
    {
        return $minimum + (hexdec(substr(hash('sha256', 'rhu-dates-v2-'.$key), 0, 7)) % ($maximum - $minimum + 1));
    }
}
