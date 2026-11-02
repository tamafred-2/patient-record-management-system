@if($todayVisits === null || ($primary ?? false))
    @once
        <form method="GET" action="{{ route('dashboard') }}" role="search" aria-label="Today's visits" class="{{ ($primary ?? false) ? 'dashboard-search min-w-0 flex-[1_1_18rem]' : 'my-4 w-full sm:w-auto sm:justify-end' }} flex flex-wrap items-center gap-2">
            @if($followupSearch ?? '')<input type="hidden" name="followup_search" value="{{ $followupSearch }}">@endif
            @if($serviceId ?? null)<input type="hidden" name="service_id" value="{{ $serviceId }}"><a href="{{ route('dashboard') }}#today-visits" class="text-sm text-emerald-800 underline">All services</a>@endif
            <div class="{{ ($primary ?? false) ? 'min-w-0 flex-1' : 'w-full sm:w-72' }}"><label for="today-search" class="sr-only">Search today's visits</label><input id="today-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, patient number or queue" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700"></div>
            <x-button>Search visits</x-button>
            @if($search !== '')<a href="{{ route('dashboard') }}" class="px-2 py-2 text-sm text-emerald-800 underline">Clear visit search</a>@endif
        </form>
    @endonce
@endif
