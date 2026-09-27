<?php

namespace App\Services;

use App\Models\TreatmentRecord;
use App\Models\Visit;

class DiagnosisLabels
{
    public static function normalize(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $label)));
    }

    /** Existing doctor-entered labels only; no inferred diseases or coding catalogue. */
    public function suggestions(): array
    {
        $counts = [];
        $visits = Visit::whereHas('patient')->select('id');
        foreach (TreatmentRecord::whereIn('visit_id', $visits)->select('diagnoses')->cursor() as $record) {
            $seen = [];
            foreach ($record->diagnoses ?? [] as $label) {
                $key = self::normalize($label);
                if ($key !== '' && ! isset($seen[$key])) {
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                    $seen[$key] = true;
                }
            }
        }
        uksort($counts, fn ($a, $b) => ($counts[$b] <=> $counts[$a]) ?: strcmp($a, $b));

        return array_slice(array_keys($counts), 0, 100);
    }
}
