<x-layout title="Midwife care">
    <h1 class="text-3xl font-semibold">Midwife care visits</h1>
    <p class="mt-2 text-slate-600">Information Staff registers a visit under Midwife care before a care service is recorded. Dates follow Asia/Manila.</p>
    @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }}</x-alert>@endif
    <x-visit-filters :search="$search" :date="$date" />
    @include('midwife-care.table')
    <div class="mt-5">{{ $visits->links() }}</div>
</x-layout>
