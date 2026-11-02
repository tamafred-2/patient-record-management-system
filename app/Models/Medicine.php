<?php

namespace App\Models;

use App\Support\DispensingQuantities;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    public const FORMS = ['Tablet', 'Capsule', 'Syrup / suspension (bottle)', 'Sachet', 'Ointment / cream (tube)', 'Drops (bottle)', 'Inhaler', 'Injection (vial / ampule)', 'Other'];

    // Stock that expires within this many days is flagged as expiring soon.
    public const EXPIRY_WARNING_DAYS = 60;

    public const STATUSES = ['in_stock' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock', 'expiring' => 'Expiring soon', 'expired' => 'Has expired stock'];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public static function lookupKey(string $name, ?string $strength, string $form): string
    {
        $normalize = fn (?string $value) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));

        return $normalize($name).'|'.$normalize($strength).'|'.$normalize($form);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class)->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return trim($this->name.' '.$this->strength).' ('.$this->form.')';
    }

    public static function today(): string
    {
        return CarbonImmutable::now('Asia/Manila')->toDateString();
    }

    /** Usable stock in hundredths: remaining quantities of batches that have not expired. */
    public function available(): int
    {
        return $this->batches->filter(fn (MedicineBatch $batch) => ! $batch->isExpired())
            ->sum(fn (MedicineBatch $batch) => DispensingQuantities::hundredths((string) $batch->quantity_remaining));
    }

    public function expiredStock(): int
    {
        return $this->batches->filter(fn (MedicineBatch $batch) => $batch->isExpired())
            ->sum(fn (MedicineBatch $batch) => DispensingQuantities::hundredths((string) $batch->quantity_remaining));
    }

    public function nearestExpiry(): ?CarbonImmutable
    {
        $batch = $this->batches->first(fn (MedicineBatch $batch) => ! $batch->isExpired() && $batch->expiry_date && $batch->quantity_remaining > 0);

        return $batch ? CarbonImmutable::parse($batch->expiry_date->toDateString(), 'Asia/Manila') : null;
    }

    /** @return list<string> Status keys for badges; a medicine can be low on stock and expiring at the same time. */
    public function statuses(): array
    {
        $available = $this->available();
        $statuses = [];
        if ($available === 0) {
            $statuses[] = 'out';
        } elseif ($available <= DispensingQuantities::hundredths((string) $this->reorder_level)) {
            $statuses[] = 'low';
        } else {
            $statuses[] = 'in_stock';
        }
        $expiry = $this->nearestExpiry();
        if ($expiry && $expiry->lte(CarbonImmutable::parse(self::today(), 'Asia/Manila')->addDays(self::EXPIRY_WARNING_DAYS))) {
            $statuses[] = 'expiring';
        }
        if ($this->expiredStock() > 0) {
            $statuses[] = 'expired';
        }

        return $statuses;
    }
}
