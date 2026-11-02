<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPES = ['RECEIVED' => 'Stock received', 'RELEASED' => 'Released to patient', 'RETURNED' => 'Returned (release voided)', 'ADJUSTED' => 'Adjustment'];

    public const ADJUSTMENT_REASONS = ['Physical count correction', 'Damaged', 'Expired - disposed', 'Recalled', 'Transferred out', 'Other'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dispensing(): BelongsTo
    {
        return $this->belongsTo(Dispensing::class);
    }
}
