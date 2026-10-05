<x-fixed-visit-table :empty="$visits->isEmpty()" label="Patient visits, scroll to view more">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">Patient visits</caption>
        <thead class="sticky top-0 z-10 bg-slate-100"><tr><th class="p-3" scope="col">Visit</th><th class="p-3" scope="col">Patient</th><th class="p-3" scope="col">Service</th><th class="p-3" scope="col">Queue reference</th><th class="p-3" scope="col">Status</th>@if($monitorActions ?? false)<th class="p-3" scope="col">Action</th>@endif</tr></thead>
        <tbody>@if($visits->isNotEmpty())@include('visits.rows')@endif</tbody>
    </table>
</x-fixed-visit-table>
<div class="mt-4">{{ $visits->links() }}</div>
