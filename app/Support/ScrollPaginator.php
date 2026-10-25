<?php

namespace App\Support;

use Illuminate\Pagination\CursorPaginator;

class ScrollPaginator extends CursorPaginator
{
    public int $matchingTotal = 0;

    public function total(): int
    {
        return $this->matchingTotal;
    }
}
