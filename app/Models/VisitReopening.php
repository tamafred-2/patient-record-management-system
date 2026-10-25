<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitReopening extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['previous_completed_at' => 'datetime'];
    }

    public function reopenedBy()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
