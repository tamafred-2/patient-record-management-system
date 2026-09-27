<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentRecord extends Model
{
    public const QUESTIONS = ['asthma' => 'Asthma', 'chest_pain' => 'Chest Pain', 'seizure' => 'Seizure', 'fainting' => 'Fainting', 'palpitations' => 'Palpitations', 'fever' => 'Fever', 'cough_colds' => 'Cough/Colds', 'allergy' => 'Allergy (Food/Drug)', 'family_hypertension' => 'Family History of Hypertension', 'taking_medicine' => 'Currently Taking Medicine'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return array_merge(array_fill_keys(array_keys(self::QUESTIONS), 'boolean'), ['diagnoses' => 'array', 'lmp' => 'date', 'objective_snapshot' => 'array', 'lock_version' => 'integer']);
    }
}
