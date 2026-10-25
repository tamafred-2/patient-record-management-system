<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispensingItem extends Model
{
    // What happened to the quantity still remaining after this entry; null keeps it open for a later release.
    public const OUTCOMES = ['BUY_OUTSIDE' => 'Patient will buy outside', 'DECLINED' => 'Patient declined'];

    protected $fillable = ['prescription_item_id', 'quantity_dispensed', 'released_as', 'remainder_outcome', 'quantity_closed', 'outcome_note'];

    protected function casts(): array
    {
        return ['quantity_dispensed' => 'decimal:2', 'quantity_closed' => 'decimal:2'];
    }
}
