<x-layout title="Doctor visits">
    <h1 class="text-3xl font-semibold">Doctor visits</h1>
    <p class="mt-2 text-slate-600">Visits appear here after vital signs are recorded. Review the nurse assessment before consultation. This list includes all services.</p>
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }}@if($record) · {{ ['with_itr' => 'With saved ITR', 'without_itr' => 'Without saved ITR', 'issued' => 'Issued prescriptions'][$record] }}@endif · {{ $visits->total() }} visits</p>
    <div class="my-6 grid items-end gap-4 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.45fr)_minmax(0,1fr)]">
    <form method="GET" action="{{ route('consultations.index') }}" aria-label="Filter doctor visit records" class="flex min-w-0 items-end gap-2">
        <input type="hidden" name="search" value="{{ $search }}">
        @if($allDates)<input type="hidden" name="all" value="1">@else<input type="hidden" name="date" value="{{ $date }}">@endif
        <div class="min-w-0 flex-1">
            <label for="record-filter" class="mb-2 block text-sm font-medium">Record filter</label>
            <select id="record-filter" name="record" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
                <option value="" @selected(!$record)>All visits</option>
                <option value="with_itr" @selected($record === 'with_itr')>With saved ITR</option>
                <option value="without_itr" @selected($record === 'without_itr')>Without saved ITR</option>
                <option value="issued" @selected($record === 'issued')>Issued prescriptions</option>
            </select>
        </div>
        <x-button class="shrink-0 cursor-pointer whitespace-nowrap" aria-label="Apply record filter">Apply</x-button>
    </form>
    <form method="GET" aria-label="Filter visits by date" class="flex min-w-0 flex-wrap items-end gap-2 sm:flex-nowrap" x-data="{ all: {{ $allDates ? 'true' : 'false' }} }">
        <input type="hidden" name="search" value="{{ $search }}">
        @if($record)<input type="hidden" name="record" value="{{ $record }}">@endif
        <div class="min-w-0 flex-1" :class="all ? 'max-w-[180px]' : ''">
            <label for="date-scope" class="mb-2 block text-sm font-medium">Visit date</label>
            <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
            <select id="date-scope" name="all" @change="all = $event.target.value === '1'" class="min-w-[135px] flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
                <option value="0" @selected(!$allDates)>Specific date</option>
                <option value="1" @selected($allDates)>All dates</option>
            </select>
            <div class="min-w-[155px] flex-1" x-show="!all" @if($allDates) x-cloak @endif>
                <label for="date" class="sr-only">Specific date</label>
                <input id="date" name="date" type="date" data-history-date max="{{ now('Asia/Manila')->toDateString() }}" value="{{ $date }}" :disabled="all" :required="!all" @disabled($allDates) @required(!$allDates) class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700">
            </div>
            </div>
        </div>
        <x-button class="shrink-0 cursor-pointer whitespace-nowrap" aria-label="Filter by date">Filter</x-button>
    </form>
    <form method="GET" aria-label="Search visits" class="flex min-w-0 items-end gap-2">
        @if($allDates)<input type="hidden" name="all" value="1">@endif
        @if($record)<input type="hidden" name="record" value="{{ $record }}">@endif
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="min-w-0 flex-1"><x-input name="search" label="Patient name or queue number" type="search" :value="$search" maxlength="100" /></div>
        <x-button class="shrink-0 cursor-pointer whitespace-nowrap">Search</x-button>
    </form>
    </div>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <div class="rounded-xl border border-slate-200 bg-white">
        <x-scroll-queue :paginator="$visits" id="visits" label="Visits, scroll to load more">
<table class="dashboard-visits-table w-full text-left text-sm">
            <caption class="sr-only">Visits for doctor review</caption>
            <thead class="sticky top-0 z-10 bg-slate-100 text-slate-500"><tr><th scope="col" class="p-3">Patient</th><th scope="col" class="p-3">Visit / service</th><th scope="col" class="p-3">Queue reference</th><th scope="col" class="p-3">Vital signs</th><th scope="col" class="p-3">Action</th></tr></thead>
            <tbody>
                @foreach($visits as $visit)
                    <tr class="border-t border-slate-100">
                        <td class="p-3"><strong>{{ $visit->patient->full_name }}</strong><p class="text-slate-500">{{ $visit->patient->patient_number }}</p></td>
                        <td class="p-3">{{ $visit->visit_number }}<p class="text-slate-500">{{ $visit->service->name }}</p></td>
                        <td class="p-3">{{ $visit->queue_reference ?? 'Not recorded' }}<x-visit-priority :visit="$visit" class="mt-1 block w-fit" /></td>
                        <td class="p-3">Recorded @if($visit->pendingAssessmentRequest)<span class="mt-1 block font-semibold text-amber-800">Reassessment requested</span>@endif</td>
                        <td class="p-3"><a class="font-semibold text-emerald-800 underline" href="{{ route('consultations.show', [$visit->patient, $visit]) }}">Review visit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
</x-scroll-queue>
    </div>
</x-layout>
