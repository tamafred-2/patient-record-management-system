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

    public static function forPrescription(Prescription $prescription): array
    {
        $released = [];
        foreach ($prescription->dispensings as $release) {
            foreach ($release->items as $item) {
                $released[$item->prescription_item_id] = ($released[$item->prescription_item_id] ?? 0) + self::hundredths($item->quantity_dispensed);
            }
        }
        $result = [];
        foreach ($prescription->items as $item) {
            $given = $released[$item->id] ?? 0;
            $result[$item->id] = ['released' => $given, 'remaining' => self::hundredths($item->quantity_prescribed) - $given];
        }

        return $result;
    }
}
