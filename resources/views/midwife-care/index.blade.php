<x-layout title="Midwife care">
    <h1 class="text-3xl font-semibold">Midwife care visits</h1>
    <p class="mt-2 text-slate-600">Information Staff registers a visit under Midwife care before a care service is recorded.</p>
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }}</x-alert>@endif
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" />
    @include('midwife-care.table')
    <div class="mt-5">{{ $visits->links() }}</div>
</x-layout>
