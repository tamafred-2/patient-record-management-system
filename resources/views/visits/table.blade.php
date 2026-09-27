<div class="overflow-auto"
    @if(($scrollAfterTen ?? false) && $visits->count() > 10)
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
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Patient visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-100"><tr><th class="p-3" scope="col">Visit</th><th class="p-3" scope="col">Patient</th><th class="p-3" scope="col">Service</th><th class="p-3" scope="col">Queue reference</th><th class="p-3" scope="col">Status</th>@if(($dashboardActions ?? false) || ($monitorActions ?? false))<th class="p-3" scope="col">Action</th>@endif</tr></thead>
        <tbody>
            @forelse($visits as $visit)
                <tr class="border-t border-slate-100">
                    <td class="p-3">@can('view', $visit)<a class="font-semibold text-emerald-800 underline" href="{{ route('patients.visits.show', [$visit->patient_id, $visit]) }}">{{ $visit->visit_number }}</a>@else<span class="font-semibold">{{ $visit->visit_number }}</span>@endcan<span class="block text-slate-500">{{ $visit->visit_date->format('M j, Y') }}</span></td>
                    <td class="p-3">{{ $patient->full_name ?? $visit->patient->full_name }}<span class="block text-xs text-slate-500">{{ $patient->patient_number ?? $visit->patient->patient_number }}</span></td>
                    <td class="p-3">{{ $visit->service->name }}</td><td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}</td><td class="p-3">{{ $visit->status }}</td>
                    @if(($dashboardActions ?? false) || ($monitorActions ?? false))
                        <td class="p-3">
                            @if($monitorActions ?? false)
                                @can('visits.monitor')<a class="font-semibold text-emerald-800 underline" href="{{ route('visits.monitor', [$visit->patient_id, $visit]) }}">View details</a>@endcan
                            @else
                            @can('consultations.view')<a class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$visit->patient, $visit]) }}">Review visit</a>
                            @elsecan('eligibility.view')<a class="font-semibold text-emerald-800 underline" href="{{ route('eligibility.edit', [$visit->patient, $visit]) }}">Open confirmation</a>@endcan
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ (($dashboardActions ?? false) || ($monitorActions ?? false)) ? 6 : 5 }}" class="p-6 text-slate-500">No matching visits.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@unless($scrollAfterTen ?? false)<div class="mt-4">{{ $visits->links() }}</div>@endunless
