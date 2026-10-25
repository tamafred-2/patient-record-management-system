<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function isSeniorOn(string $date): bool
    {
        return $this->birth_date && $this->birth_date->copy()->addYears(60)->toDateString() <= $date;
    }

    public const DEMOGRAPHICS = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status',
        'contact_number', 'barangay', 'municipality', 'province', 'emergency_contact_name', 'emergency_contact_number',
        'guardian_name', 'guardian_relationship', 'guardian_phone',
    ];

    protected $fillable = self::DEMOGRAPHICS;

    public function scopeSearchIdentity(Builder $query, ?string $search): Builder
    {
        foreach (preg_split('/\s+/u', trim($search ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function (Builder $match) use ($pattern) {
                foreach (['patient_number', 'first_name', 'middle_name', 'last_name', 'suffix'] as $field) {
                    $match->orWhereRaw("LOWER({$field}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return $query;
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'lock_version' => 'integer'];
    }

    public function getFullNameAttribute(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix]));
    }

    public static function duplicateKey(string $first, string $last): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($first))).'|'.mb_strtolower(preg_replace('/\s+/u', ' ', trim($last))));
    }

    public function hasSameDemographics(array $data): bool
    {
        $normalize = fn ($value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));

        foreach (self::DEMOGRAPHICS as $field) {
            $stored = $field === 'birth_date' ? $this->birth_date?->format('Y-m-d') : $this->{$field};
            if ($normalize($stored) !== $normalize($data[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
