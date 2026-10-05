<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaboratoryRecord extends Model
{
    public const STATUSES = ['PENDING' => 'Pending', 'PERFORMED_RHU' => 'Completed at RHU', 'EXTERNAL_ADVISED' => 'External laboratory advised'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'result_rows' => 'array', 'completed_at' => 'datetime'];
    }
}
