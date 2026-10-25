<?php

namespace App\Models;

use App\Models\Concerns\HasPermittedTests;
use App\Support\FollowupAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Standing authority for a nurse to order laboratory tests for patients they assessed, until a doctor turns it off.
// Changing the permitted tests closes this row and starts a new one, so each order keeps the exact version it used.
class NurseAuthorization extends Model
{
    use HasPermittedTests;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'allow_any_test' => 'boolean', 'allowed_tests' => 'array'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function activeFor(User $nurse): ?self
    {
        return self::active()->where('nurse_id', $nurse->id)->with('doctor')->latest('id')->first();
    }

    // Active and granted by a doctor who can still authorize; only then may the nurse use it.
    public function usable(): bool
    {
        return ! $this->revoked_at && FollowupAccess::doctor($this->doctor);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nurse_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
