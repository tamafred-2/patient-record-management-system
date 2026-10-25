<x-layout title="Visit details">
    <x-back-link :href="route('patients.show', $patient)">Back to patient history</x-back-link>
    <p class="mt-5 font-semibold text-emerald-800">{{ $patient->patient_number }} · {{ $patient->full_name }}</p>
    <h1 class="mt-2 text-3xl font-semibold">{{ $visit->visit_number }}</h1>
    <x-button variant="secondary" :href="route('visits.stub', [$patient, $visit])" class="mt-4">Print / Reprint stub</x-button>
    <dl class="mt-8 grid gap-6 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd></div>
        <div><dt class="text-sm text-slate-500">Requested service</dt><dd>{{ $visit->service->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Queue number</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Record status</dt><dd>{{ $visit->status }}</dd></div>
    </dl>
    <x-visit-priority-panel :patient="$patient" :visit="$visit" class="mt-6" />
    <x-visit-follow-up-panel :patient="$patient" :visit="$visit" class="mt-6" />
    <x-visit-documents :visit="$visit" class="mt-6" />
    @can('vitals.view')
        <a class="mt-6 inline-block font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$patient, $visit]) }}">Open vital signs</a>
    @endcan
    @can('eligibility.view')
        <a class="mt-6 inline-block font-semibold text-emerald-800 underline" href="{{ route('eligibility.edit', [$patient, $visit]) }}">Open confirmation</a>
    @endcan
    @can('complete', $visit)
        <x-button :href="route('visits.completion', [$patient, $visit])" class="mt-6">{{ $visit->status === 'OPEN' ? 'Review and complete visit' : 'View completion details' }}</x-button>
    @endcan
</x-layout>
