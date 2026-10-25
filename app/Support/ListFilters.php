<?php

namespace App\Support;

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
}
