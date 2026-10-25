@php($queueLabel = $scrollLabel ?? (($lazyVisits ?? false) ? "Today's visits, scroll to load more" : 'Patient visits, scroll to load more'))
<x-scroll-queue :paginator="$visits" :id="($lazyVisits ?? false) ? 'dashboard-'.($sectionType ?? 'visits') : 'visits'" :next="$nextVisitsUrl ?? null" :label="$queueLabel">
    <table class="w-full text-left text-sm {{ ($lazyVisits ?? false) ? 'dashboard-visits-table' : '' }}">
        <caption class="sr-only">Patient visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-100"><tr><th class="p-3" scope="col">Visit</th><th class="p-3" scope="col">Patient</th><th class="p-3" scope="col">{{ $serviceHeading ?? 'Service' }}</th><th class="p-3" scope="col">Queue reference</th><th class="p-3" scope="col">Status</th>@if(($dashboardActions ?? false) || ($monitorActions ?? false))<th class="p-3" scope="col">Action</th>@endif</tr></thead>
        <tbody>
            @unless(($lazyVisits ?? false) && $visits->isEmpty())
                @include($rowsView ?? 'visits.rows')
            @endunless
        </tbody>
    </table>
    @if($visits->isEmpty())<p class="p-8 text-center text-slate-500">{{ ($search ?? '') !== '' || ($serviceId ?? null) ? 'No matching visits.' : ($emptyTitle ?? 'No visits today') }}</p>@endif
</x-scroll-queue>
