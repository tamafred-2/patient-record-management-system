<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    public const FIELDS = ['systolic_bp', 'diastolic_bp', 'temperature', 'pulse_rate', 'height_cm', 'weight_kg', 'respiratory_rate', 'oxygen_saturation'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['oxygen_saturation' => 'decimal:2', 'recorded_at' => 'datetime', 'lock_version' => 'integer', 'temperature' => 'decimal:2', 'height_cm' => 'decimal:2', 'weight_kg' => 'decimal:2'];
    }
}
