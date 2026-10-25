<x-layout title="Pharmacy">
    <h1 class="text-3xl font-semibold">Pharmacy</h1>
    <p class="mt-2 text-slate-600">Issued prescriptions by visit date.</p>
    <x-visit-filters :search="$search" :date="$date" :all-dates="$allDates" />
    <p class="mt-3 text-sm font-semibold text-emerald-800">{{ $allDates ? 'All dates' : $date }} ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    @include('pharmacy.table')
    
</x-layout>
