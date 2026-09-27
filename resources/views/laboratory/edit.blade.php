<x-layout title="Laboratory service">
    <a href="{{ route('laboratory.show',[$patient,$visit]) }}" class="text-emerald-800 underline">Back to visit laboratory records</a>
    <h1 class="mt-5 text-3xl font-semibold">Laboratory service</h1>
    <p class="my-5 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }} &middot; {{ $visit->visit_number }} &middot; {{ $visit->visit_date->format('M j, Y') }}</p>
    @include('laboratory.form')
</x-layout>
