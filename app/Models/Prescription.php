<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    protected $guarded = ['*'];

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
}
