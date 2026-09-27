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

    public function scopeSearchPatientOrQueue(Builder $query, ?string $search): Builder
    {
        $terms = preg_split('/\s+/u', trim($search ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function (Builder $match) use ($pattern) {
                $match->whereRaw("LOWER(queue_reference) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('patient', function (Builder $patient) use ($pattern) {
                        $patient->where(function (Builder $names) use ($pattern) {
                            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
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
        return ['visit_date' => 'date', 'completed_at' => 'datetime'];
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

    public function dispositionRecords(): HasMany
    {
        return $this->hasMany(DispositionRecord::class);
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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
