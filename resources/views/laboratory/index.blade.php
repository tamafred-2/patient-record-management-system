<x-layout title="Laboratory">
    <h1 class="text-3xl font-semibold">Laboratory</h1>
    <p class="mt-2 text-slate-600">Record laboratory services for existing visits. This list includes all services.</p>
    <div class="my-6 flex flex-wrap items-end gap-5 xl:flex-nowrap">
    <form method="GET" aria-label="Filter laboratory records" class="flex shrink-0 flex-wrap items-end gap-3">
        <input type="hidden" name="search" value="{{ $search }}">
        <input type="hidden" name="date" value="{{ $date }}">
        @if($allDates)<input type="hidden" name="all" value="1">@endif
        <div>
            <label for="record-filter" class="mb-2 block text-sm font-medium">Record filter</label>
            <select id="record-filter" name="record" class="w-44 rounded-lg border border-slate-300 bg-white px-3 py-2.5">
                <option value="all" @selected($record === 'all')>All visits</option>
                <option value="with_records" @selected($record === 'with_records')>With lab records</option>
            </select>
        </div>
        <x-button aria-label="Apply record filter">Apply</x-button>
    </form>
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" :record="$record" :inline="true" />
    </div>
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} &middot; {{ $record === 'with_records' ? 'With lab records' : 'All visits' }} &middot; {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <x-fixed-visit-table :empty="$visits->isEmpty()">
    <table class="w-full text-left text-sm">
            <caption class="sr-only">Visits for laboratory services</caption>
            <thead class="sticky top-0 z-10 bg-slate-50 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / service</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Lab records</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>
                @foreach($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-3">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}</td>
                        <td class="p-3">{{ $visit->laboratory_records_count }}</td>
                        <td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('laboratory.show', [$visit->patient, $visit]) }}">Open lab records</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-fixed-visit-table>
    <div class="mt-6">{{ $visits->links() }}</div>
</x-layout>
