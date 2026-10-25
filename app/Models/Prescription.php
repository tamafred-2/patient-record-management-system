<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    public const DRAFT = 'DRAFT';

    public const ISSUED = 'ISSUED';

    protected $guarded = ['*'];

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', self::ISSUED);
    }

    protected function casts(): array
    {
        return ['prescribed_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function dispensings(): HasMany
    {
        return $this->hasMany(Dispensing::class)->orderByDesc('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('id');
    }

    public function activeDispensings(): HasMany
    {
        return $this->dispensings()->whereNull('voided_at');
    }

    // Non-voided pharmacy entries that actually gave medicine, as opposed to only recording buy-outside or declined.
    public function releasedDispensings(): HasMany
    {
        return $this->activeDispensings()->whereHas('items', fn (Builder $items) => $items->where('quantity_dispensed', '>', 0));
    }

    public function scopeWithUnreleasedOutcome(Builder $query, string $outcome): Builder
    {
        return $query->whereHas('activeDispensings.items', fn (Builder $items) => $items->where('remainder_outcome', $outcome));
    }
}
