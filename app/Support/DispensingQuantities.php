<?php

namespace App\Support;

use App\Models\Prescription;

class DispensingQuantities
{
    // Input has already passed plain decimal validation or is an Eloquent decimal cast.
    public static function hundredths(string $value): int
    {
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $value): string
    {
        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Per prescription item, in hundredths: released at the RHU, closed without release (buy outside or
     * declined, by outcome), and still remaining. Voided entries count for nothing.
     *
     * @return array<int, array{released: int, closed: int, outcomes: array<string, int>, remaining: int}>
     */
    public static function forPrescription(Prescription $prescription): array
    {
        $released = [];
        $outcomes = [];
        foreach ($prescription->dispensings as $release) {
            if ($release->voided_at !== null) {
                continue;
            }
            foreach ($release->items as $item) {
                $id = $item->prescription_item_id;
                $released[$id] = ($released[$id] ?? 0) + self::hundredths($item->quantity_dispensed);
                if ($item->remainder_outcome !== null) {
                    $outcomes[$id][$item->remainder_outcome] = ($outcomes[$id][$item->remainder_outcome] ?? 0) + self::hundredths((string) $item->quantity_closed);
                }
            }
        }
        $result = [];
        foreach ($prescription->items as $item) {
            $given = $released[$item->id] ?? 0;
            $closed = array_sum($outcomes[$item->id] ?? []);
            $result[$item->id] = ['released' => $given, 'closed' => $closed, 'outcomes' => $outcomes[$item->id] ?? [], 'remaining' => self::hundredths($item->quantity_prescribed) - $given - $closed];
        }

        return $result;
    }
}
