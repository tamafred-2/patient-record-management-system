<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalCertificate extends Model
{
    protected $guarded = ['*'];

    public const TEMPLATES = ['consultation' => 'Consultation / treatment', 'examination' => 'Medical examination'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
