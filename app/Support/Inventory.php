<?php

namespace App\Support;

use App\Models\Dispensing;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

// Every stock change goes through here, inside the caller's transaction, so batch balances and the movement
// ledger always agree. Quantities are handled in hundredths, like dispensing quantities.
class Inventory
{
    public static function receive(Medicine $medicine, int $quantity, array $details, User $user): MedicineBatch
    {
        $amount = DispensingQuantities::format($quantity);
        $batch = MedicineBatch::forceCreate([
            'medicine_id' => $medicine->id, 'batch_number' => $details['batch_number'] ?? null, 'expiry_date' => $details['expiry_date'] ?? null,
            'received_on' => $details['received_on'], 'quantity_received' => $amount, 'quantity_remaining' => $amount,
            'supplier' => $details['supplier'] ?? null, 'remarks' => $details['remarks'] ?? null, 'created_by' => $user->id,
        ]);
        self::record($medicine, $batch, 'RECEIVED', $quantity, $user, null, $details['supplier'] ?? null);

        return $batch;
    }

    /** Deducts from the earliest-expiring usable batches first; expired stock is never released. */
    public static function release(Medicine $medicine, int $quantity, Dispensing $dispensing, User $user): void
    {
        $batches = $medicine->batches()->where('quantity_remaining', '>', 0)->lockForUpdate()->get()
            ->reject(fn (MedicineBatch $batch) => $batch->isExpired());
        $available = $batches->sum(fn (MedicineBatch $batch) => DispensingQuantities::hundredths((string) $batch->quantity_remaining));
        if ($quantity > $available) {
            throw ValidationException::withMessages(['stock' => $medicine->label().' has only '.DispensingQuantities::display($available).' in stock. Release no more than that, or mark the rest as bought outside.']);
        }
        $left = $quantity;
        foreach ($batches as $batch) {
            if ($left === 0) {
                break;
            }
            $take = min($left, DispensingQuantities::hundredths((string) $batch->quantity_remaining));
            self::adjustBatch($batch, -$take);
            self::record($medicine, $batch, 'RELEASED', -$take, $user, $dispensing);
            $left -= $take;
        }
    }

    /** Puts back exactly what a voided release took, batch by batch. */
    public static function returnRelease(Dispensing $dispensing, User $user): void
    {
        $taken = StockMovement::where('dispensing_id', $dispensing->id)->where('type', 'RELEASED')->with('batch')->lockForUpdate()->get();
        foreach ($taken as $movement) {
            $amount = -DispensingQuantities::hundredths(ltrim((string) $movement->quantity, '-'));
            $batch = MedicineBatch::whereKey($movement->medicine_batch_id)->lockForUpdate()->firstOrFail();
            self::adjustBatch($batch, -$amount);
            self::record(Medicine::findOrFail($movement->medicine_id), $batch, 'RETURNED', -$amount, $user, $dispensing, 'Release #'.$dispensing->id.' voided');
        }
    }

    /** A signed manual correction on one batch; stock can never go below zero. */
    public static function adjust(MedicineBatch $batch, int $change, string $reason, User $user): void
    {
        $batch = MedicineBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
        if (DispensingQuantities::hundredths((string) $batch->quantity_remaining) + $change < 0) {
            throw ValidationException::withMessages(['adjust_quantity' => 'This batch has only '.DispensingQuantities::display((string) $batch->quantity_remaining).' remaining. The adjustment cannot make it negative.']);
        }
        self::adjustBatch($batch, $change);
        self::record($batch->medicine, $batch, 'ADJUSTED', $change, $user, null, $reason);
    }

    private static function adjustBatch(MedicineBatch $batch, int $change): void
    {
        $batch->quantity_remaining = DispensingQuantities::format(DispensingQuantities::hundredths((string) $batch->quantity_remaining) + $change);
        $batch->save();
    }

    private static function record(Medicine $medicine, ?MedicineBatch $batch, string $type, int $change, User $user, ?Dispensing $dispensing = null, ?string $reason = null): void
    {
        StockMovement::forceCreate([
            'medicine_id' => $medicine->id, 'medicine_batch_id' => $batch?->id, 'type' => $type,
            'quantity' => ($change < 0 ? '-' : '').DispensingQuantities::format(abs($change)),
            'dispensing_id' => $dispensing?->id, 'reason' => $reason, 'created_by' => $user->id,
        ]);
    }
}
