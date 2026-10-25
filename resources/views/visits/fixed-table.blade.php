<x-scroll-queue :paginator="$visits" id="visits" label="Visits, scroll to load more">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Patient visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-100"><tr><th class="p-3" scope="col">Visit</th><th class="p-3" scope="col">Patient</th><th class="p-3" scope="col">Service</th><th class="p-3" scope="col">Queue reference</th><th class="p-3" scope="col">Status</th>@if($monitorActions ?? false)<th class="p-3" scope="col">Action</th>@endif</tr></thead>
        <tbody>@if($visits->isNotEmpty())@include('visits.rows')@endif</tbody>
    </table>
</x-scroll-queue>

