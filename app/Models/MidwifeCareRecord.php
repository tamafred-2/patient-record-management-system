<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    public const PRENATAL_FIELDS = ['lmp', 'gravida', 'para', 'fundal_height_cm', 'fetal_heart_tone'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date', 'lock_version' => 'integer', 'lmp' => 'date', 'gravida' => 'integer', 'para' => 'integer', 'fundal_height_cm' => 'decimal:1', 'fetal_heart_tone' => 'integer'];
    }

    // Expected date of delivery by Naegele's rule (LMP + 280 days). Arithmetic only; not a clinical assessment.
    public function expectedDelivery(): ?Carbon
    {
        return $this->lmp?->copy()->addDays(280);
    }

    // Age of gestation on the visit date, from LMP, as "N weeks D days".
    public function ageOfGestation(): ?string
    {
        if (! $this->lmp || ! $this->visit) {
            return null;
        }
        $days = (int) $this->lmp->diffInDays($this->visit->visit_date);

        return intdiv($days, 7).' weeks '.($days % 7).' days';
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
