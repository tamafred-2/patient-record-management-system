<?php

namespace App\Models;

use App\Models\Concerns\HasPermittedTests;
use App\Notifications\LabOrderingAuthorized;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Legacy per-visit, single-order grants. New authority uses NurseAuthorization.
class FollowupAuthorization extends Model
{
    use HasPermittedTests;

    protected $guarded = ['*'];

    public function scopeAvailableTo(Builder $query, User $nurse): Builder
    {
        return $query->where('nurse_id', $nurse->id)->whereNull('used_at')->whereNull('revoked_at')
            ->whereHas('doctor', fn ($doctor) => $doctor->where('is_active', true)->role('Doctor / Medical Officer')->permission('followups.authorize')->permission('consultations.view'));
    }

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'revoked_at' => 'datetime', 'allow_any_test' => 'boolean', 'allowed_tests' => 'array'];
    }

    // The nurse's unread alert for this grant no longer applies once it is used or withdrawn.
    public function clearNurseAlert(): void
    {
        $this->nurse->unreadNotifications()->where('type', LabOrderingAuthorized::class)->get()
            ->filter(fn ($notification) => ($notification->data['authorization_id'] ?? null) === $this->id)->each->markAsRead();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
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
