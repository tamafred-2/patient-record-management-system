<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositionRecord extends Model
{
    public const TYPES = ['FORMAL_REFERRAL' => 'Formal referral recorded', 'EMERGENCY_REFERRAL' => 'Emergency referral recorded', 'ADVISED_HIGHER_FACILITY' => 'Advice to seek a higher facility', 'CERTIFICATE_REQUEST' => 'Medical certificate requested'];

    protected $guarded = ['*'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
