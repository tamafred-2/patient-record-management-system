<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispositionRecord extends Model
{
    public const TYPES = ['FORMAL_REFERRAL' => 'Formal referral recorded', 'EMERGENCY_REFERRAL' => 'Emergency referral recorded', 'ADVISED_HIGHER_FACILITY' => 'Advice to seek a higher facility', 'CERTIFICATE_REQUEST' => 'Medical certificate requested'];

    protected $guarded = ['*'];
}
