<?php

namespace App\Models;

use App\Support\TableSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $guarded = ['*'];

    public const OPEN = 'OPEN';

    public const COMPLETED = 'COMPLETED';

    public const PRIORITY_RANKS = ['REGULAR' => 0, 'PRIORITY' => 1, 'EMERGENCY' => 2];

    public const PRIORITY_LABELS = ['REGULAR' => 'Regular', 'PRIORITY' => 'Priority', 'EMERGENCY' => 'Emergency'];

    public const PRIORITY_REASONS = ['PWD' => 'Person with disability (PWD)', 'SENIOR' => 'Senior citizen', 'PREGNANT' => 'Pregnant', 'OTHER' => 'Other'];

    public static function priorityRules(bool $senior = false): array
    {
        return [
            'priority' => ['required', 'string', 'in:'.($senior ? 'PRIORITY,EMERGENCY' : implode(',', array_keys(self::PRIORITY_RANKS)))],
            'priority_reason' => ['nullable', 'required_if:priority,PRIORITY', 'prohibited_unless:priority,PRIORITY', 'string', 'in:'.implode(',', array_keys(self::PRIORITY_REASONS))],
            'priority_note' => ['nullable', 'required_if:priority_reason,OTHER', 'prohibited_unless:priority_reason,OTHER', 'string', 'max:150'],
        ];
    }

    public function applyPriority(array $data): void
    {
        $this->priority = $data['priority'];
        $this->priority_rank = self::PRIORITY_RANKS[$data['priority']];
        $this->priority_reason = $data['priority'] === 'PRIORITY' ? $data['priority_reason'] : null;
        $this->priority_note = $this->priority_reason === 'OTHER' ? trim($data['priority_note']) : null;
    }

    public function priorityLabel(): string
    {
        $label = self::PRIORITY_LABELS[$this->priority ?? 'REGULAR'] ?? 'Regular';
        if ($this->priority === 'PRIORITY' && $this->priority_reason) {
            $label .= ' · '.($this->priority_reason === 'OTHER' && $this->priority_note ? $this->priority_note : self::PRIORITY_REASONS[$this->priority_reason]);
        }

        return $label;
    }

    // Open visits where this nurse can order laboratory tests: a usable legacy per-visit grant, or their standing
    // authorization for patients whose assessment they saved. $standing is the nurse's usable standing authority, if any.
    public function scopeOrderableBy(Builder $query, User $nurse, bool $standing): Builder
    {
        return $query->where('status', self::OPEN)
            ->whereHas('vitalSign', fn ($vital) => $vital->where(fn ($by) => $by->where('recorded_by', $nurse->id)->orWhere('updated_by', $nurse->id)))
            ->when(! $standing, fn ($match) => $match->whereHas('followupAuthorizations', fn ($grant) => $grant->availableTo($nurse)));
    }

    // Emergency first, then priority, then regular; newest first within a level. Both columns are non-null for cursor pagination.
    public function scopePriorityFirst(Builder $query): Builder
    {
        return $query->orderByDesc('priority_rank')->orderByDesc('id');
    }

    // Direct laboratory visits and encounters with saved laboratory work only.
    // Retain historical records even when their original service was different.
    public function scopeForLaboratory(Builder $query): Builder
    {
        return $query->where(fn (Builder $eligible) => $eligible
            ->whereHas('service', fn (Builder $service) => $service->withTrashed()->where('seed_key', 'LABORATORY'))
            ->orWhereHas('laboratoryRecords'));
    }

    public function scopeFilterHistory(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->where(function (Builder $match) use ($filters) {
                $match->where(fn (Builder $reference) => TableSearch::apply($reference, $filters['search'], ['visit_number', 'queue_reference']))
                    ->orWhereHas('service', fn (Builder $service) => TableSearch::apply($service, $filters['search'], ['name']));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query;
    }

    public static function historyFilterRules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:OPEN,COMPLETED'], 'priority' => ['nullable', 'in:REGULAR,PRIORITY,EMERGENCY']];
    }

    public function scopeSearchPatientOrQueue(Builder $query, ?string $search): Builder
    {
        foreach (TableSearch::patterns($search) as $pattern) {
            $query->where(fn (Builder $match) => TableSearch::matchAny($match, $pattern, ['queue_reference'])
                ->orWhereHas('patient', fn (Builder $patient) => $patient->where(fn (Builder $names) => TableSearch::matchAny($names, $pattern, Patient::SEARCH_COLUMNS))));
        }

        return $query;
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    protected function casts(): array
    {
        return ['visit_date' => 'date', 'completed_at' => 'datetime', 'follow_up_on' => 'date', 'follow_up_set_at' => 'datetime', 'follow_up_notified_at' => 'datetime'];
    }

    public function followUpSetter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_set_by');
    }

    public function scopeMidwifeCareVisits(Builder $query): Builder
    {
        return $query->whereHas('patient')->whereHas('service', fn (Builder $service) => $service->where('seed_key', 'MIDWIFE_CARE'));
    }

    public function midwifeCareRecords(): HasMany
    {
        return $this->hasMany(MidwifeCareRecord::class);
    }

    public function scopeVaccinationVisits(Builder $query): Builder
    {
        return $query->whereHas('patient')->whereHas('service', fn (Builder $service) => $service->where('seed_key', 'VACCINATION'));
    }

    public function vaccinationRecords(): HasMany
    {
        return $this->hasMany(VaccinationRecord::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function eligibilityCheck(): HasOne
    {
        return $this->hasOne(EligibilityCheck::class);
    }

    public function laboratoryRecords(): HasMany
    {
        return $this->hasMany(LaboratoryRecord::class);
    }

    public function followupAuthorizations(): HasMany
    {
        return $this->hasMany(FollowupAuthorization::class);
    }

    public function dispositionRecords(): HasMany
    {
        return $this->hasMany(DispositionRecord::class);
    }

    public function medicalCertificates(): HasMany
    {
        return $this->hasMany(MedicalCertificate::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function treatmentRecord(): HasOne
    {
        return $this->hasOne(TreatmentRecord::class);
    }

    public function vitalSign(): HasOne
    {
        return $this->hasOne(VitalSign::class);
    }

    public function assessmentRequests(): HasMany
    {
        return $this->hasMany(AssessmentRequest::class);
    }

    public function pendingAssessmentRequest(): HasOne
    {
        return $this->hasOne(AssessmentRequest::class)->where('status', 'PENDING');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
