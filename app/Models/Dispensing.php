<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispensing extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['dispensed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispensingItem::class);
    }

    public function pharmacist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_id');
    }
}
