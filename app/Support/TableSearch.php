<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class TableSearch
{
    // Columns are supplied by application code, never by request input.
    public static function apply(Builder $query, string $search, array $columns): void
    {
        foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function (Builder $match) use ($columns, $pattern) {
                foreach ($columns as $column) {
                    $match->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }
    }
}
