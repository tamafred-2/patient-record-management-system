<?php

namespace App\Models;

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

    public const DEMOGRAPHICS = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'birth_date', 'sex', 'civil_status',
        'contact_number', 'barangay', 'municipality', 'province', 'emergency_contact_name', 'emergency_contact_number',
    ];

    protected $fillable = self::DEMOGRAPHICS;

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
}
