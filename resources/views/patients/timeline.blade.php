<x-layout title="Patient file">
    @can('view', $patient)
        <x-back-link :href="route('patients.show', $patient)">Back to patient profile</x-back-link>
    @else
        {{-- Doctors reach the timeline from a visit and may not have registry access. --}}
        <x-back-link :href="url()->previous() !== url()->current() ? url()->previous() : route('dashboard')">Back</x-back-link>
    @endcan
    <h1 class="mt-5 text-3xl font-semibold">Patient file</h1>
    <p class="mt-3 font-medium">{{ $patient->full_name }} &middot; {{ $patient->patient_number }}</p>
    <p class="mt-2 text-sm text-slate-500">Registered {{ $patient->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Events show original recording times.</p>
    <div class="mt-4 flex flex-wrap gap-3">
        <x-button variant="secondary" :href="route('print-center.patient', $patient)">Documents &amp; printouts</x-button>
        <x-button :href="route('print-center.summary', $patient)">Print patient summary</x-button>
    </div>
    <div class="mt-6 rounded-xl border border-slate-200 bg-white">
        <x-visit-history-filters embedded class="p-4" />
        <x-scroll-queue flush :paginator="$visits" id="visits" kind="cards" label="Record history, scroll to load more"><div data-queue-items>@forelse($visits as $visit)
            @include('patients.visit-record')
        @empty<p>No visits recorded.</p>@endforelse</div></x-scroll-queue>
    </div>
</x-layout>
