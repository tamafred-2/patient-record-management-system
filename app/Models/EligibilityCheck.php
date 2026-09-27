<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EligibilityCheck extends Model
{
    protected $fillable = ['status', 'remarks', 'philhealth_confirmed'];

    protected function casts(): array
    {
        return ['philhealth_confirmed' => 'boolean', 'verified_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
