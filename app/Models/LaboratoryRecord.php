<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LaboratoryRecord extends Model
{
    public const STATUSES = ['PENDING' => 'Pending', 'PERFORMED_RHU' => 'Completed at RHU', 'EXTERNAL_ADVISED' => 'External laboratory advised'];

    protected $guarded = ['*'];

    public function authorizingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizing_doctor_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function followupAuthorization(): BelongsTo
    {
        return $this->belongsTo(FollowupAuthorization::class);
    }

    public function nurseAuthorization(): BelongsTo
    {
        return $this->belongsTo(NurseAuthorization::class);
    }

    // Placed by a nurse under doctor authority (standing or legacy per-visit), not directly by a doctor.
    public function isNurseOrder(): bool
    {
        return (bool) ($this->nurse_authorization_id || $this->followup_authorization_id);
    }

    public function authorizedAt(): ?Carbon
    {
        return $this->nurseAuthorization?->created_at ?? $this->followupAuthorization?->created_at;
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'result_rows' => 'array', 'completed_at' => 'datetime'];
    }
}
