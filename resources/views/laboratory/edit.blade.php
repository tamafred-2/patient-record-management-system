<x-layout title="Laboratory service">
    <x-back-link :href="route('laboratory.show',[$patient,$visit])">Back to visit laboratory records</x-back-link>
    <h1 class="mt-5 text-3xl font-semibold">Laboratory service</h1>
    <p class="my-5 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }} &middot; {{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</p>
    @include('laboratory.request-origin', ['entry' => $record])
    @if($record->availability_status === 'PERFORMED_RHU' && ($record->result_rows || filled($record->result)))<a href="{{ route('prints.laboratory-result', [$patient, $visit, $record]) }}" class="mb-5 inline-block font-semibold text-emerald-800 underline">Print laboratory result</a>@endif
    @include('laboratory.form')
</x-layout>
