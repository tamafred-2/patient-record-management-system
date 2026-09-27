<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MidwifeCareRecord extends Model
{
    public const TYPES = [
        'PRENATAL' => 'Prenatal follow-up',
        'POSTPARTUM' => 'Postpartum care',
        'FAMILY_PLANNING' => 'Family planning',
        'CHILD_HEALTH' => 'Child health',
        'NEWBORN' => 'Newborn care',
        'COMMUNITY' => 'Community / home visit',
    ];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date', 'lock_version' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
