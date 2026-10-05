<x-layout title="Patient visits">
    <h1 class="text-3xl font-semibold">Patient visits</h1>
    <p class="mt-2 text-slate-600">{{ $allDates ? 'All dates' : "$from to $to" }} &middot; {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }}</x-alert>@endif
    <x-visit-filters :search="$search" :from="$from" :to="$to" :range="true" :all-dates="$allDates" />
    @if(!$allDates)<a href="{{ route('visits.index', ['all' => 1, 'search' => $search]) }}" class="text-sm text-emerald-800 underline">Show all dates</a>@endif
    @can('visits.create')<p class="mt-3 text-sm text-slate-600">Start a visit from the patient's profile.</p>@endcan
    <section class="mt-6">@include('visits.fixed-table', ['monitorActions' => auth()->user()->can('visits.monitor')])</section>
</x-layout>
