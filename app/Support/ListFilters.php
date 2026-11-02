<?php

namespace App\Support;

use App\Models\Visit;

// Shared validation for list filters, so every date picker and search box applies the same limits.
class ListFilters
{
    /** An optional historical date: today in Manila or earlier. */
    public static function pastDate(): array
    {
        return ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Manila')->toDateString()];
    }

    public static function search(): array
    {
        return ['nullable', 'string', 'max:100'];
    }

    public static function priority(): array
    {
        return ['nullable', 'in:'.implode(',', array_keys(Visit::PRIORITY_LABELS))];
    }

    /** The priority dropdown shared by every visit list. */
    public static function priorityFilter(): array
    {
        return ['priority' => ['label' => 'Priority', 'options' => Visit::PRIORITY_LABELS]];
    }
}
