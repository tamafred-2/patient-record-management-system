@props(['paginator', 'id' => 'visits', 'label' => 'Records, scroll to load more', 'kind' => 'table', 'next' => null, 'previous' => null, 'flush' => false])
@php
    $nextUrl = $next ?? $paginator->nextPageUrl();
    $previousUrl = $previous ?? $paginator->previousPageUrl();
    if (request()->header('X-Scroll-Queue') === $id) {
        request()->attributes->set('scroll_queue_payload', ['html' => (string) $slot, 'next' => $nextUrl, 'previous' => $previousUrl, 'count' => $paginator->count()]);
    }
@endphp
<div {{ $attributes->class(['scroll-queue bg-white', 'my-4 rounded-xl border border-slate-200' => !$flush, 'rounded-b-xl' => $flush]) }} data-scroll-queue="{{ $id }}" data-queue-kind="{{ $kind }}" data-next="{{ $nextUrl }}" data-previous="{{ $previousUrl }}">
    <div class="queue-controls flex items-center justify-between gap-3 px-4 py-2 text-sm">
        <a data-queue-previous href="{{ $previousUrl ?: '#' }}" @if(!$previousUrl) hidden @endif class="font-semibold text-emerald-800 underline">Load earlier records</a>
        <span class="text-slate-500">{{ !$nextUrl && !$previousUrl ? $paginator->count().' '.($paginator->count() === 1 ? 'record' : 'records') : '10 records per batch' }}</span>
    </div>
    <div data-queue-viewport class="queue-viewport overflow-auto" tabindex="0" role="region" aria-label="{{ $label }}">
        {{ $slot }}
        @if($paginator->isEmpty() && $id === 'visits')<div role="status" class="p-8 text-center text-slate-500"><p>No matching visits</p><p class="mt-2">Try a different date or search.</p></div>@endif
        <div aria-hidden="true" class="queue-end-space"></div>
    </div>
    <div class="queue-controls flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
        <span data-queue-status aria-live="polite">{{ $paginator->isEmpty() ? 'No matching records.' : ($nextUrl ? 'Scroll for more records.' : 'End of records.') }}</span>
        <a data-queue-next href="{{ $nextUrl ?: '#' }}" @if(!$nextUrl) hidden @endif class="sr-only font-semibold text-emerald-800 underline focus:not-sr-only">Load more records</a>
    </div>
</div>
