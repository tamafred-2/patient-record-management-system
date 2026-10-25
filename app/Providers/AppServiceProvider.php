<?php

namespace App\Providers;

use App\Support\ScrollPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CursorPaginator::class, ScrollPaginator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::macro('scrollPaginate', function (string $cursorName = 'cursor') {
            $total = request()->hasHeader('X-Scroll-Queue') ? 0 : (clone $this)->count();
            $paginator = $this->cursorPaginate(10, ['*'], $cursorName)->withQueryString();
            $paginator->matchingTotal = $total;

            return $paginator;
        });
        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.simple');
    }
}
