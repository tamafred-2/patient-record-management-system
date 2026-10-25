<?php

namespace App\Support;

use Illuminate\Database\QueryException;

class DatabaseBusy
{
    public static function matches(QueryException $exception): bool
    {
        $connection = config('database.connections.'.$exception->getConnectionName().'.driver');
        $code = (int) ($exception->errorInfo[1] ?? 0);

        return $connection === 'sqlite' && in_array($code & 0xFF, [5, 6], true);
    }
}
