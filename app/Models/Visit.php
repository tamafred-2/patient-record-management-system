<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $guarded = ['*'];

    public const PRIORITY_RANKS = ['REGULAR' => 0, 'PRIORITY' => 1, 'EMERGENCY' => 2];

    public const PRIORITY_LABELS = ['REGULAR' => 'Regular', 'PRIORITY' => 'Priority', 'EMERGENCY' => 'Emergency'];

    public const PRIORITY_REASONS = ['PWD' => 'Person with disability (PWD)', 'SENIOR' => 'Senior citizen', 'PREGNANT' => 'Pregnant', 'OTHER' => 'Other'];

    public static function priorityRules(): array
    {
        return [
            'priority' => ['required', 'string', 'in:'.implode(',', array_keys(self::PRIORITY_RANKS))],
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
        return $query->where('status', 'OPEN')
            ->whereHas('vitalSign', fn ($vital) => $vital->where(fn ($by) => $by->where('recorded_by', $nurse->id)->orWhere('updated_by', $nurse->id)))
            ->when(! $standing, fn ($match) => $match->whereHas('followupAuthorizations', fn ($grant) => $grant->availableTo($nurse)));
    }

    // Emergency first, then priority, then regular; newest first within a level. Both columns are non-null for cursor pagination.
    public function scopePriorityFirst(Builder $query): Builder
    {
        return $query->orderByDesc('priority_rank')->orderByDesc('id');
    }

    public function scopeSearchPatientOrQueue(Builder $query, ?string $search): Builder
    {
        $terms = preg_split('/\s+/u', trim($search ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function (Builder $match) use ($pattern) {
                $match->whereRaw("LOWER(queue_reference) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('patient', function (Builder $patient) use ($pattern) {
                        $patient->where(function (Builder $names) use ($pattern) {
                            foreach (['patient_number', 'first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                                $names->orWhereRaw("LOWER({$field}) LIKE ? ESCAPE '!'", [$pattern]);
                            }
                        });
                    });
            });
        }

        return $query;
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
