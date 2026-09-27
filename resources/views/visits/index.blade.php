<x-layout title="Patient visits">
    <h1 class="text-3xl font-semibold">Patient visits</h1>
    <p class="mt-2 text-slate-600">{{ $from }} to {{ $to }} ? Asia/Manila ? {{ $visits->total() }} visits</p>
    @if($errors->any())<x-alert type="error" class="mt-4">{{ $errors->first() }}</x-alert>@endif
    <x-visit-filters :search="$search" :from="$from" :to="$to" :range="true" />
    @can('visits.create')<p class="mt-3 text-sm text-slate-600">Start a visit from the patient's profile.</p>@endcan
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-4">@include('visits.table', ['monitorActions' => auth()->user()->can('visits.monitor')])</section>
</x-layout>
