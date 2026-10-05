<x-layout title="Vaccination">
    <h1 class="text-3xl font-semibold">Vaccination visits</h1>
    <p class="mt-2 text-slate-600">Information Staff registers a visit under Vaccination before a vaccine is recorded.</p>
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }}</x-alert>@endif
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" />
    @include('vaccinations.table')
    <div class="mt-5">{{ $visits->links() }}</div>
</x-layout>
