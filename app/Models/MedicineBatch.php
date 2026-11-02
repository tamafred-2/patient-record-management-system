<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineBatch extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['expiry_date' => 'date', 'received_on' => 'date', 'quantity_received' => 'decimal:2', 'quantity_remaining' => 'decimal:2'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // A batch expires at the end of its expiry date (Philippine time) and is then never released.
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->toDateString() < Medicine::today();
    }
}
