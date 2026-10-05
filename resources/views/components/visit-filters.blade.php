@props(['search' => '', 'date' => null, 'from' => null, 'to' => null, 'range' => false, 'allDates' => false, 'record' => null, 'inline' => false])

<div class="{{ $inline ? 'flex min-w-0 flex-wrap items-end gap-5 xl:flex-nowrap' : 'my-6 flex flex-wrap items-end justify-between gap-5' }}">
    <form method="GET" aria-label="Filter visits by date" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="search" value="{{ $search }}">
        @if($record)<input type="hidden" name="record" value="{{ $record }}">@endif
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
        <div class="{{ $inline ? 'w-full sm:w-56' : 'w-full sm:w-72' }}"><x-input name="search" label="Patient name or queue number" type="search" :value="$search" maxlength="100" /></div>
        <x-button class="cursor-pointer">Search</x-button>
    </form>
</div>
