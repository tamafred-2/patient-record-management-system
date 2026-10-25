@props(['label' => 'Search records', 'filters' => []])
<form method="GET" action="{{ url()->current() }}" aria-label="{{ $label }}" class="my-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <div class="min-w-48 flex-1"><label for="table-search" class="mb-2 block text-sm font-medium">{{ $label }}</label><input id="table-search" type="search" name="search" value="{{ request('search') }}" maxlength="100" class="w-full rounded-lg border border-slate-300 p-3"></div>
    @foreach($filters as $name => $filter)
        <div><label for="filter-{{ $name }}" class="mb-2 block text-sm font-medium">{{ $filter['label'] }}</label><select id="filter-{{ $name }}" name="{{ $name }}" class="w-full rounded-lg border border-slate-300 p-3"><option value="">All</option>@foreach($filter['options'] as $value => $label)<option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $label }}</option>@endforeach</select></div>
    @endforeach
    <x-button>Apply filters</x-button><a href="{{ url()->current() }}" class="px-2 py-3 text-sm font-semibold text-emerald-800 underline">Reset</a>
</form>
