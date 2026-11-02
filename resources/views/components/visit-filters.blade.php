@props(['search' => '', 'date' => null, 'from' => null, 'to' => null, 'range' => false, 'allDates' => false, 'record' => null, 'inline' => false, 'filters' => []])
@php
    // $filters: name => ['label' => …, 'options' => [value => label]]. Selected values survive date changes and searches.
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700';
@endphp
<div class="{{ $inline ? 'flex min-w-0 flex-wrap items-end gap-5 xl:flex-nowrap' : 'my-6 flex flex-wrap items-end justify-between gap-5' }}">
    <form method="GET" aria-label="Filter visits by date" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="search" value="{{ $search }}">
        @if($record)<input type="hidden" name="record" value="{{ $record }}">@endif
        @foreach($filters as $name => $filter)@if(filled(request($name)))<input type="hidden" name="{{ $name }}" value="{{ request($name) }}">@endif @endforeach
        <x-visit-date-scope :date="$date" :from="$from" :to="$to" :range="$range" :all-dates="$allDates" />
        <x-button class="cursor-pointer" aria-label="Filter by date">{{ $inline ? 'Filter' : 'Filter by date' }}</x-button>
    </form>
    <form method="GET" aria-label="Search visits" class="flex w-full flex-wrap items-end gap-3 sm:ml-auto sm:w-auto">
        @if($allDates)<input type="hidden" name="all" value="1">@endif
        @if($record)<input type="hidden" name="record" value="{{ $record }}">@endif
        @if($range)
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">
        @else
            <input type="hidden" name="date" value="{{ $date }}">
        @endif
        @foreach($filters as $name => $filter)
            <div><label for="visit-filter-{{ $name }}" class="mb-2 block text-sm font-medium">{{ $filter['label'] }}</label>
                <select id="visit-filter-{{ $name }}" name="{{ $name }}" class="{{ $selectClass }}"><option value="">All</option>@foreach($filter['options'] as $value => $optionLabel)<option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $optionLabel }}</option>@endforeach</select></div>
        @endforeach
        {{-- The button sits beside the search box so phones keep room for the list. --}}
        <div class="flex w-full items-end gap-3 sm:w-auto">
            <div class="{{ $inline ? 'min-w-0 flex-1 sm:w-56 sm:flex-none' : 'min-w-0 flex-1 sm:w-72 sm:flex-none' }}"><x-input name="search" label="Patient name or queue number" type="search" :value="$search" maxlength="100" /></div>
            <x-button class="shrink-0 cursor-pointer">Search</x-button>
        </div>
    </form>
</div>
