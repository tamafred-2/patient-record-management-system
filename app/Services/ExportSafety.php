<?php

namespace App\Services;

use App\Exceptions\ExportUnavailable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExportSafety
{
    public const LOCK_KEY = 'pdf-export:render';

    public function run(int $userId, Closure $work): mixed
    {
        $lock = null;
        $acquired = false;
        try {
            $key = 'pdf-export:user:'.$userId;
            if (RateLimiter::tooManyAttempts($key, config('export_safety.requests_per_minute'))) {
                throw new ExportUnavailable('Too many download attempts. Please wait a minute before trying again.', 429, max(1, RateLimiter::availableIn($key)));
            }
            RateLimiter::hit($key, 60);
            $lock = Cache::lock(self::LOCK_KEY, config('export_safety.lock_seconds'));
            $acquired = $lock->get();
            if (! $acquired) {
                throw new ExportUnavailable('Another PDF is being prepared. Please wait a moment, then try your download again.');
            }
            $this->checkMemory();

            return $work();
        } catch (ValidationException|AuthorizationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Do not copy rendered patient content, SQL bindings or paths into this log.
            $this->logFailure('PDF export could not complete.', $exception);
            throw new ExportUnavailable('The PDF could not be prepared safely. Try a smaller selection or Summary only. If it still fails, contact the system administrator.');
        } finally {
            if ($acquired) {
                try {
                    $lock->release();
                } catch (Throwable $exception) {
                    $this->logFailure('PDF export lock release failed.', $exception);
                }
            }
        }
    }

    private function logFailure(string $message, Throwable $exception): void
    {
        try {
            Log::error($message, ['exception_class' => $exception::class]);
        } catch (Throwable) {
            // A full disk or unavailable log service must not hide the safe response.
        }
    }

    public function checkHtml(string $html): void
    {
        if (strlen($html) > config('export_safety.maximum_html_bytes')) {
            throw new ExportUnavailable('This PDF contains too much content to prepare in one download. Select fewer dates or records, or use Summary only. For a single visit, ask the administrator to review the document size.', 422, 0);
        }
        $this->checkMemory();
    }

    public function checkMemory(): void
    {
        $limit = $this->memoryLimitBytes();
        if ($limit !== null && $limit - $this->usedMemoryBytes() < config('export_safety.minimum_memory_headroom_bytes')) {
            throw new ExportUnavailable('There is not enough memory headroom to start this PDF safely. Try a smaller selection or Summary only. If this continues, contact the system administrator.');
        }
    }

    protected function memoryLimitBytes(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '-1' || ! preg_match('/^(\d+)\s*([KMG]?)$/i', $value, $parts)) {
            return null;
        }

        return (int) $parts[1] * (1024 ** array_search(strtoupper($parts[2]), ['', 'K', 'M', 'G'], true));
    }

    protected function usedMemoryBytes(): int
    {
        return memory_get_usage(true);
    }
}
