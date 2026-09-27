@props(['search' => '', 'date' => null, 'from' => null, 'to' => null, 'range' => false])

<div class="my-6 flex flex-wrap items-end justify-between gap-5">
    <form method="GET" aria-label="Filter visits by date" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="search" value="{{ $search }}">
        @if($range)
            <x-input name="from" label="From date" type="date" :value="$from" required />
            <x-input name="to" label="To date" type="date" :value="$to" required />
        @else
            <x-input name="date" label="Visit date" type="date" :value="$date" required />
        @endif
        <x-button class="cursor-pointer">Filter by date</x-button>
    </form>
    <form method="GET" aria-label="Search visits" class="flex w-full flex-wrap items-end gap-3 sm:ml-auto sm:w-auto">
        @if($range)
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">
        @else
            <input type="hidden" name="date" value="{{ $date }}">
        @endif
        <div class="w-full sm:w-72"><x-input name="search" label="Patient name or queue number" type="search" :value="$search" maxlength="100" /></div>
        <x-button class="cursor-pointer">Search</x-button>
    </form>
</div>
