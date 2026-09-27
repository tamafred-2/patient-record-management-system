<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    public const FIELDS = ['medicine_name', 'strength', 'dosage', 'frequency', 'duration', 'instructions', 'quantity_prescribed'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['quantity_prescribed' => 'decimal:2'];
    }
}
