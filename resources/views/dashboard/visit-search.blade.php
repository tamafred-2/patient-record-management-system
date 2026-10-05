@if($todayVisits === null || ($primary ?? false))
    @once
        <form method="GET" action="{{ route('dashboard') }}" aria-label="Search today's visits" class="{{ ($primary ?? false) ? '' : 'my-4' }} flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
            @if($serviceId ?? null)<input type="hidden" name="service_id" value="{{ $serviceId }}"><a href="{{ route('dashboard') }}#today-visits" class="text-sm text-emerald-800 underline">All services</a>@endif
            <div class="w-full sm:w-72"><label for="today-search" class="sr-only">Search today's visits</label><input id="today-search" name="search" value="{{ $search }}" maxlength="100" placeholder="Name, patient number or queue" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-emerald-700 focus:outline-2 focus:outline-emerald-700"></div>
            <x-button>Search visits</x-button>
            @if($search !== '')<a href="{{ route('dashboard') }}" class="px-2 py-2 text-sm text-emerald-800 underline">Clear visit search</a>@endif
        </form>
    @endonce
@endif
