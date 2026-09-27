<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispensingItem extends Model
{
    protected $fillable = ['prescription_item_id', 'quantity_dispensed'];

    protected function casts(): array
    {
        return ['quantity_dispensed' => 'decimal:2'];
    }
}
