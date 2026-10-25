<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class TableSearch
{
    // Columns are supplied by application code, never by request input.
    public static function apply(Builder $query, string $search, array $columns): void
    {
        foreach (self::patterns($search) as $pattern) {
            $query->where(fn (Builder $match) => self::matchAny($match, $pattern, $columns));
        }
    }

    /** One case-insensitive LIKE pattern per whitespace-separated term, with LIKE metacharacters treated as literal input. */
    public static function patterns(?string $search): array
    {
        return array_map(
            fn (string $term) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%',
            preg_split('/\s+/u', trim($search ?? ''), -1, PREG_SPLIT_NO_EMPTY),
        );
    }

    public static function matchAny(Builder $query, string $pattern, array $columns): Builder
    {
        foreach ($columns as $column) {
            $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
        }

        return $query;
    }
}
