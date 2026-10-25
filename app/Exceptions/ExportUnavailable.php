<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ExportUnavailable extends HttpException
{
    public function __construct(string $message, int $status = 503, int $retryAfter = 15)
    {
        parent::__construct($status, $message, null, $retryAfter > 0 ? ['Retry-After' => (string) $retryAfter] : []);
    }
}
