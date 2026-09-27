@props(['title', 'description' => null])
<section {{ $attributes->class(['min-w-0 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6']) }} x-data="{ flipped: false }">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ $title }}</h2>
        <button type="button" @click="flipped = !flipped" :aria-pressed="flipped.toString()" aria-label="Flip {{ $title }} card" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 px-3 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4m0 0 4-4M4 7l4 4m-4 6h16m0 0-4-4m4 4-4 4"/></svg>
            <span x-text="flipped ? 'Show chart' : 'Show counts'">Show counts</span>
        </button>
    </div>
    @if($description)<p class="mb-5 text-sm text-slate-600">{{ $description }}</p>@endif
    <div class="analytics-flip">
        <div class="analytics-flip-inner" :class="{ 'is-flipped': flipped }">
            <div class="analytics-flip-face" :inert="flipped" :aria-hidden="flipped.toString()">{{ $chart }}</div>
            <div x-cloak class="analytics-flip-face analytics-flip-back" :inert="!flipped" :aria-hidden="(!flipped).toString()">{{ $counts }}</div>
        </div>
    </div>
</section>
