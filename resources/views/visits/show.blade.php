<x-layout title="Visit details">
    <a href="{{ route('patients.show', $patient) }}" class="text-emerald-800 underline">Back to patient history</a>
    <p class="mt-6 font-semibold text-emerald-800">{{ $patient->patient_number }} · {{ $patient->full_name }}</p>
    <h1 class="mt-2 text-3xl font-semibold">{{ $visit->visit_number }}</h1>
    <dl class="mt-8 grid gap-6 rounded-xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Visit date</dt><dd>{{ $visit->visit_date->format('M j, Y') }}</dd></div>
        <div><dt class="text-sm text-slate-500">Requested service</dt><dd>{{ $visit->service->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Existing queue reference</dt><dd>{{ $visit->queue_reference ?? 'Not recorded' }}</dd></div>
        <div><dt class="text-sm text-slate-500">Record status</dt><dd>{{ $visit->status }}</dd></div>
    </dl>
    @can('vitals.view')
        <a class="mt-6 inline-block font-semibold text-emerald-800 underline" href="{{ route('assessments.edit', [$patient, $visit]) }}">Open vital signs</a>
    @endcan
    @can('eligibility.view')
        <a class="mt-6 inline-block font-semibold text-emerald-800 underline" href="{{ route('eligibility.edit', [$patient, $visit]) }}">Open confirmation</a>
    @endcan
    @can('complete', $visit)
        <a class="mt-6 inline-block rounded-lg bg-emerald-800 px-4 py-3 font-semibold text-white" href="{{ route('visits.completion', [$patient, $visit]) }}">{{ $visit->status === 'OPEN' ? 'Review and complete visit' : 'View completion details' }}</a>
    @endcan
</x-layout>
