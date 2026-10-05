<div class="overflow-auto"
    @if($lazyVisits ?? false)
        tabindex="0" role="region" aria-label="{{ $scrollLabel ?? "Today's visits, scroll to load more" }}" style="height: 744px"
        x-data="{
            next: @js($nextVisitsUrl), loading: false, failed: false,
            async load() {
                if (!this.next || this.loading) return;
                this.loading = true; this.failed = false;
                try {
                    const response = await fetch(this.next, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Could not load visits');
                    const data = await response.json();
                    this.$el.querySelector('tbody').insertAdjacentHTML('beforeend', data.html);
                    this.next = data.next;
                } catch (error) { this.failed = true; }
                finally { this.loading = false; }
            }
        }"
        @scroll.debounce.150ms="if (!failed && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 160) load()"
        :aria-busy="loading"
    @elseif(($scrollAfterTen ?? false) && $visits->count() > 10)
        tabindex="0" role="region" aria-label="Today's visits, scroll to view more"
        x-data="{ height: null, observer: null,
            measure() {
                const table = this.$el.querySelector('table');
                const row = table.tBodies[0]?.rows[9];
                if (row) this.height = Math.ceil(row.getBoundingClientRect().bottom - table.getBoundingClientRect().top);
            },
            init() { this.$nextTick(() => { this.measure(); this.observer = new ResizeObserver(() => this.measure()); this.observer.observe(this.$el.querySelector('table')); }); },
            destroy() { this.observer?.disconnect(); }
        }"
        :style="height ? { maxHeight: height + 'px' } : {}"
    @endif
>
    <table class="w-full text-left text-sm {{ ($lazyVisits ?? false) ? 'dashboard-visits-table' : '' }}">
        <caption class="sr-only">Patient visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-100"><tr><th class="p-3" scope="col">Visit</th><th class="p-3" scope="col">Patient</th><th class="p-3" scope="col">{{ $serviceHeading ?? 'Service' }}</th><th class="p-3" scope="col">Queue reference</th><th class="p-3" scope="col">Status</th>@if(($dashboardActions ?? false) || ($monitorActions ?? false))<th class="p-3" scope="col">Action</th>@endif</tr></thead>
        <tbody>
            @unless(($lazyVisits ?? false) && $visits->isEmpty())
                @include($rowsView ?? 'visits.rows')
            @endunless
        </tbody>
    </table>
    @if(($lazyVisits ?? false) && $visits->isEmpty())
        <div class="flex flex-col items-center justify-center px-6 text-center" style="height: 700px" role="status">
            <div class="mb-4 rounded-full bg-slate-100 p-4 text-slate-400">
                <svg aria-hidden="true" class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z M16 3v4 M8 3v4 M3 11h18 M9 16h6" /></svg>
            </div>
            <p class="font-semibold text-slate-700">{{ ($search ?? '') !== '' || ($serviceId ?? null) ? 'No matching visits.' : ($emptyTitle ?? 'No visits today') }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ ($search ?? '') !== '' || ($serviceId ?? null) ? 'Try a different search or clear the filters.' : "Today's registered visits will appear here." }}</p>
        </div>
    @endif
    @if($lazyVisits ?? false)
        <div class="p-3 text-center text-sm" x-show="next || loading || failed">
            <p aria-live="polite" x-text="loading ? 'Loading more visits…' : (failed ? 'Unable to load visits. Please retry.' : '')"></p>
            <button type="button" class="cursor-pointer rounded-lg border border-emerald-700 px-4 py-2 font-semibold text-emerald-800 disabled:opacity-50" @click="load()" :disabled="loading" x-show="next" x-text="failed ? 'Retry' : 'Load more visits'"></button>
        </div>
        <noscript><a class="block p-3 text-emerald-800 underline" href="{{ route('visits.index') }}">Open the paginated patient visits list</a></noscript>
    @endif
</div>
@unless($scrollAfterTen ?? false)<div class="mt-4">{{ $visits->links() }}</div>@endunless
